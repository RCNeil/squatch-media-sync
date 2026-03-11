# Squatch Media Sync

A very simple WordPress plugin that searches through files in the specified `wp-content/uploads` folder and adds them to the Media Library if they do not already exist.

## How It Works

- Allows the user to select a **top-level directory** inside `wp-content/uploads`.
- Scans the selected directory **recursively**, including any subdirectories.
- Processes the files in **batches by subdirectory** to avoid timeouts.
- Adds files that are not already present in the Media Library.
- Displays **live progress and logging output** while syncing.
- Skips files that are already in the Media Library.

## Supported File Types

The plugin works with any file type supported by WordPress attachments, including:

- Images (`jpg`, `jpeg`, `png`, `gif`, `webp`)
- Documents (`pdf`, `doc`, `docx`)
- Other media files that WordPress allows in the Media Library

## Notes

- The plugin does **not modify or move files** in the uploads directory.
- Only files that do not already exist in the Media Library will be added.
- It is recommended to **create a backup before running a large sync**.

This is not an actively supported plugin.  Use are your own risk. 
