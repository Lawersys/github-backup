<?php
/*
Plugin Name: GitHub Backup
Description: A simple plugin to backup WordPress site to GitHub.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Function to backup database
function backup_database() {
    global $wpdb;
    $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
    $backup_file = fopen(WP_CONTENT_DIR . '/backup-database.sql', 'w');

    foreach ($tables as $table) {
        $create_table = $wpdb->get_row('SHOW CREATE TABLE ' . $table[0], ARRAY_N);
        fwrite($backup_file, $create_table[1] . ";\n\n");

        $rows = $wpdb->get_results('SELECT * FROM ' . $table[0], ARRAY_N);
        foreach ($rows as $row) {
            $values = array_map([$wpdb, 'escape'], $row);
            fwrite($backup_file, 'INSERT INTO ' . $table[0] . ' VALUES (\'' . implode("', '", $values) . '\');' . "\n");
        }
        fwrite($backup_file, "\n\n");
    }
    fclose($backup_file);
}

// Function to backup files
function backup_files() {
    $zip = new ZipArchive();
    $filename = WP_CONTENT_DIR . '/backup-files.zip';

    if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
        exit("Unable to create zip file\n");
    }

    $rootPath = realpath(ABSPATH);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath), RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
}

// Function to push backup to GitHub
function push_to_github() {
    $repo = 'https://github.com/your-username/your-repo.git';
    $branch = 'main';
    $token = 'your-github-token';

    backup_database();
    backup_files();

    $output = shell_exec('cd ' . WP_CONTENT_DIR . ' && git init && git remote add origin ' . $repo . ' && git add . && git commit -m "Backup" && git push -u origin ' . $branch);
    echo $output;
}

// Add settings page
function github_backup_menu() {
    add_menu_page('GitHub Backup', 'GitHub Backup', 'manage_options', 'github-backup', 'github_backup_page');
}
add_action('admin_menu', 'github_backup_menu');

function github_backup_page() {
    if (isset($_POST['backup'])) {
        push_to_github();
        echo '<div class="updated"><p>Backup completed and pushed to GitHub!</p></div>';
    }
    echo '<div class="wrap">';
    echo '<h2>GitHub Backup</h2>';
    echo '<form method="post">';
    echo '<input type="submit" name="backup" value="Backup Now" class="button button-primary"/>';
    echo '</form>';
    echo '</div>';
}
?>
