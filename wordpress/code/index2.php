<?php
// Specify the file name and mode ('w' for write) 
$filename = "../../wp_cus/example2.txt";

// Attempt to open the file for writing
$file = fopen($filename, "w");

// Check if the file was opened successfully
if ($file) {
    // Content to write to the file
    $content = "This is some text that will be written to the file.\n";

    // Write the content to the file
    fwrite($file, $content);

    // Close the file
    fclose($file);

    echo "File '$filename' has been created and written to successfully.";
} else {
    echo "Error: Unable to create or open the file.";
}
?>

<VirtualHost *:443>
                ServerAdmin webmaster@$domainName
                ServerName $domainName
                DocumentRoot $sitePath
        
                SSLEngine on
                SSLCertificateFile $sslCertificatePath
                SSLCertificateKeyFile $sslCertificateKeyPath
                SSLCertificateChainFile $sslCertificateChainPath
        
                # Additional SSL configuration options can be added here
        
                ErrorLog \${APACHE_LOG_DIR}/error.log
                CustomLog \${APACHE_LOG_DIR}/access.log combined
            </VirtualHost>