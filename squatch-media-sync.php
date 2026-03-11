<?php
/*
Plugin Name: Squatch Media Syncer
Plugin URI: https://squatchcreative.com
Description:Take the files in your wp-content/uploads folder and adds them to the media library 
Version: 1.002
Author: Squatch Creative
Author URI: https://squatchcreative.com
*/

$plugin_data = get_file_data(__FILE__,array('Version' => 'Version'));
$plugin_version = $plugin_data['Version'];

define('SQUATCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SQUATCH_PLUGIN_PATH', plugin_dir_path(__FILE__));



add_action('admin_menu', function() {
	add_submenu_page(
		'tools.php',       
		'Squatch Media Sync',
		'Squatch Media Sync',
		'manage_options',
		'squatch-media-sync', 
		'squatch_media_sync_page' 
	);
});

function squatch_admin_footer_text($footer_text) {
	$screen = get_current_screen();

	// Only show on our plugin page
	if ($screen && $screen->id === 'tools_page_squatch-media-sync') {
		$img_url = SQUATCH_PLUGIN_URL . 'assets/built-by-squatch.svg'; 
		ob_start();
		?>
		<span id="footer-thankyou">
			<a href="https://squatchcreative.com" title="Built By Squatch Creative" target="_blank">
				<img src="<?php echo esc_url($img_url); ?>" alt="Built By Squatch Creative" style="height:28px; vertical-align:middle;">
			</a>
		</span>
		<?php
		return ob_get_clean();
	}

	return $footer_text; // Default for other pages
}
add_filter('admin_footer_text', 'squatch_admin_footer_text');

add_action('admin_head', function() {
	$screen = get_current_screen();

	// Only add CSS on our media sync plugin page
	if($screen && $screen->id === 'tools_page_squatch-media-sync') {
		?>
		<style>
		.squatch-plugin-header .left h1 {
			margin: 0 0 8px 0;
		}
		.squatch-plugin-header .left p {
			margin: 0 0 16px 0;
			color: #555;
		}

		#media-sync-progress {
			margin-top: 10px;
			background: #eee;
			border: 1px solid #ccc;
			height: 20px;
			width: 100%;
			position: relative;
			border-radius: 6px;
			overflow: hidden;
		}
		#media-sync-progress-bar {
			background: #0073aa;
			width: 0%;
			height: 100%;
			transition: width 0.3s ease;
		}

		#media-sync-output {
			margin-top: 20px;
			max-height: 400px;
			overflow: auto;
		}

		#media-sync-summary {
			padding: 20px 0 24px 0;
			font-size: 16px;
			font-weight: bold;
		}
		#media-sync-form {
			transition:180ms ease all;
		}
		#media-sync-form.processing {
			opacity: 0.6;
			pointer-events: none;
		}
		#media-sync-form.processing button {
			cursor: not-allowed;
		}
		</style>
		<?php
	}
});



function squatch_media_sync_page() {
	$uploads_dir = WP_CONTENT_DIR . '/uploads';
	$folders = array_filter(glob($uploads_dir . '/*'), 'is_dir');

	echo '<div class="wrap">';
	echo '<div class="squatch-plugin-header"><div class="left"><h1>Squatch Media Sync</h1><p>Select a top-level folder from your wp-content/uploads folder to sync with the media library. Be sure to make a backup first.</p></div></div>';

	echo '<form id="media-sync-form">';
	echo '<select name="folder" id="sync-folder">';
	foreach($folders as $folder) {
		$folder_name = basename($folder);
		echo '<option value="' . esc_attr($folder_name) . '">' . esc_html($folder_name) . '</option>';
	}
	echo '</select>';
	echo '<button type="submit" class="button button-primary">Start Sync</button>';
	echo '</form>';

	echo '<div id="media-sync-progress"><div id="media-sync-progress-bar"></div></div>';
	echo '<div id="media-sync-output"></div>';
	echo '<div id="media-sync-summary"></div>';
	echo '</div>';
	?>
	<script>
	jQuery(document).ready(function($){
		$('#media-sync-form').on('submit', function(e){
			e.preventDefault();
			$(this).addClass('processing');
			var folder = $('#sync-folder').val();
			$('#media-sync-output').html('');
			$('#media-sync-summary').html('');
			$('#media-sync-progress-bar').css('width','0%');

			// Step 1: Get list of subdirectories
			$.post(ajaxurl, {
				action: 'media_sync_get_subdirs',
				folder: folder,
				_nonce: '<?php echo wp_create_nonce('media_sync_nonce'); ?>'
			}, function(response){
				var data = JSON.parse(response);
				var subdirs = data.subdirs;
				var total = subdirs.length;
				var index = 0;
				var added = 0;
				var existing = 0;
				var skipped = 0;

				function processNext() {
					if(index >= total) {
						$('#media-sync-summary').html('Done! Added: ' + added + ', Already in library: ' + existing + ', Skipped: ' + skipped);
						$('#media-sync-form').removeClass('processing');
						return;
					}
					var sub = subdirs[index];
					$.post(ajaxurl, {
						action: 'media_sync_process_subdir',
						folder: folder,
						subdir: sub,
						_nonce: '<?php echo wp_create_nonce('media_sync_nonce'); ?>'
					}, function(res){
						var resData = JSON.parse(res);
						added += resData.added;
						existing += resData.existing;
						skipped += resData.skipped;

						$('#media-sync-output').append(resData.output);
						index++;
						$('#media-sync-progress-bar').css('width', ((index/total)*100) + '%');
						processNext();
					});
				}

				processNext();
			});
		});
	});
	</script>
	<?php
}

// Step 1: Return list of subdirectories for batching
add_action('wp_ajax_media_sync_get_subdirs', function() {
	check_ajax_referer('media_sync_nonce', '_nonce');
	if(!current_user_can('manage_options')) wp_die('Permission denied');
	if(empty($_POST['folder'])) wp_die('No folder selected');

	$folder_name = sanitize_text_field($_POST['folder']);
	$base_dir = WP_CONTENT_DIR . '/uploads/' . $folder_name;

	if(!file_exists($base_dir)) wp_die('Folder not found');

	$subdirs = array();
	foreach(glob($base_dir . '/*', GLOB_ONLYDIR) as $dir) {
		$subdirs[] = basename($dir);
	}
	if(empty($subdirs)) $subdirs[] = ''; // no subdirs, process parent itself

	echo json_encode(['subdirs' => $subdirs]);
	wp_die();
});

// Step 2: Process one subdirectory at a time
add_action('wp_ajax_media_sync_process_subdir', function() {
	check_ajax_referer('media_sync_nonce', '_nonce');
	if(!current_user_can('manage_options')) wp_die('Permission denied');
	if(empty($_POST['folder'])) wp_die('No folder selected');

	$folder_name = sanitize_text_field($_POST['folder']);
	$subdir_name = isset($_POST['subdir']) ? sanitize_text_field($_POST['subdir']) : '';

	$base_dir = WP_CONTENT_DIR . '/uploads/' . $folder_name;
	if($subdir_name) $base_dir .= '/' . $subdir_name;

	if(!file_exists($base_dir)) wp_die('Folder not found');

	require_once(ABSPATH . 'wp-admin/includes/image.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/media.php');

	$output = '';
	$added = 0;
	$existing = 0;
	$skipped = 0;
	
	$display_subdir = $subdir_name ? $subdir_name : $folder_name;
	$output .= '<br /><br /><strong>Processing folder: ' . esc_html($display_subdir) . '</strong>';

	$all_files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($base_dir),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach($all_files as $file) {
		if(!$file->isFile()) continue;

		$full_path = $file->getRealPath();
		$filename = basename($full_path);

		if(preg_match('/-\d+x\d+(?=\.[^.]+$)/', $filename)) { $skipped++; continue; }
		if(strpos($filename, '-unsmushed') !== false) { $skipped++; continue; }

		$relative_path = str_replace(trailingslashit(WP_CONTENT_DIR . '/uploads'), '', $full_path);
		$url = content_url('uploads/' . $relative_path);
		$attachment_id = attachment_url_to_postid($url);

		if($attachment_id) {
			$output .= '<br />Already in Media Library: ' . esc_html($relative_path) . ' (ID ' . $attachment_id . ')';
			$existing++;
			continue;
		}

		$filetype = wp_check_filetype($filename, null);
		$attachment = [
			'guid'           => $url,
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name($filename),
			'post_content'   => '',
			'post_status'    => 'inherit'
		];

		$attach_id = wp_insert_attachment($attachment, $full_path);

		if(strpos($filetype['type'], 'image/') === 0) {
			$attach_data = wp_generate_attachment_metadata($attach_id, $full_path);
			wp_update_attachment_metadata($attach_id, $attach_data);
		}

		$output .= '<br />Added: ' . esc_html($relative_path) . ' (ID ' . $attach_id . ')';
		$added++;
	}

	echo json_encode([
		'output' => $output,
		'added' => $added,
		'existing' => $existing,
		'skipped' => $skipped
	]);
	wp_die();
});