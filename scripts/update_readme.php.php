<?php

/**
 * Updates the README.md file in each specified folder with a list of all markdown files in the same folder.
 *
 * @param array $folder_names  An array of folder names
 * @return void
 */
function update_readme($folder, $config = []) {
        // Define the path to the README.md file
        $readme_path = $folder . "/README.md";
        $readme_title = $folder;

        // Get all markdown files in the folder (excluding README.md)
        $markdown_files = array_filter(scandir($folder), function($file) use ($folder) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'md' && $file !== 'README.md';
        });

        // Check if we have a title
        if(isset($config['title']) === true){
            $readme_title = $config['title'];
        }

        // Initialize an empty string to store the file contents
        $file_contents = "# $readme_title\n";

        // Check if we have a description (and add it if we have it)
        if(isset($config['description']) === true){
            $file_contents .= $config['description']."\n";
        }

        $file_contents = $config['description']."\n";

        // Loop through the markdown files and add them to the file contents
        foreach ($markdown_files as $file) {
            $file_contents .= "* [$file]($folder/$file)\n";
        }

        // Write the file contents to the README.md file, replacing its current contents
        file_put_contents($readme_path, $file_contents);
}

// Define an array of folders
$folders = [
    'docs/classes' => []
];

//Loop trough the folder
foreach ($folders as $folder => $config) {
    // Call the update_readme function
    update_readme($folder, $config);
}


?>
