<?php

namespace Gemvc\Helper;

class FileHelper
{
    public string $sourceFile;
    public string $outputFile;
    public ?string $error = null;
    public ?string $secret;
    public function __construct(string $sourceFile, ?string $outputFile = null)
    {
        $this->error = null;
        $this->sourceFile =  $sourceFile;
        $this->outputFile = $outputFile ? $outputFile : $sourceFile;
        if ($this->isDestinationDirectoryExists()) {
            if (!file_exists($this->sourceFile)) { {
                    $this->error = "Source-file not found $this->sourceFile";
                }
            }
        }
    }

    public function copy(): bool
    {
        if ($this->error) {
            return false;
        }
        if (shell_exec("cp " . escapeshellarg($this->sourceFile) . " " . escapeshellarg($this->outputFile))) {
            return true;
        } else {
            $this->error = "Could not copy file $this->sourceFile to $this->outputFile";
        }
        return false;
    }

    public function move(): bool
    {
        if ($this->error) {
            return false;
        }
        if (shell_exec("mv $this->sourceFile $this->outputFile")) {
            return true;
        } else {
            $this->error = "Could not move file $this->sourceFile to $this->outputFile";
        }
        return false;
    }

    public function delete(): bool
    {
        if ($this->error) {
            return false;
        }
        if (shell_exec("rm $this->sourceFile")) {
            return true;
        } else {
            $this->error = "Could not move file $this->sourceFile to $this->outputFile";
        }
        return false;
    }

    public function moveAndEncrypt():bool
    {
        if ($this->error) {
            return false;
        }
        if (shell_exec("mv $this->sourceFile $this->outputFile")) {
            return $this->encrypt() !== false;
        } else {
            $this->error = "Could not move file $this->sourceFile to $this->outputFile";
            return false;
        }
        
    }

    /**
     * @return false|string Returns the destination path
     */
    public function encrypt(): false|string
    {
        if ($this->error) {
            return false;
        }
        if (!$this->secret) {
            $this->error = $this->error ?? "Missing secret, secret is not set";
            return false;
        }
        $fileContents = $this->readFileContents();
        if (!$fileContents) {
            return false;
        }
        $fileContents = CryptHelper::encryptString($fileContents, $this->secret);
        if (!$fileContents) {
            $this->error = "Cannot encrypt file contents" . $this->sourceFile;
            return false;
        }
        if ($this->writeFileContents($fileContents)) {
            return $this->outputFile;
        } else {
            $this->error = "Cannot encrypt file $this->outputFile";
        }
        return false;
    }

    /**
     * @return false|string Returns the destination path
     */
    public function decrypt(): false|string
    {
        if ($this->error) {
            return false;
        }
        if (!$this->secret) {
            $this->error = "Missing secret , secret is not set";
            return false;
        }
        $fileContents = $this->readFileContents();
        if (!$fileContents) {
            return false;
        }
        $fileContents = CryptHelper::decryptString($fileContents, $this->secret);
        if (!$fileContents) {
            $this->error = "Cannot decrypt file - Secret is wrong" . $this->sourceFile;
            return false;
        }
        if ($this->writeFileContents($fileContents)) {
            return $this->outputFile;
        }
        return false;
    }


    public function deleteSourceFile(): bool
    {
        if ($this->error) {
            return false;
        }
        if (file_exists($this->sourceFile)) {
            unlink($this->sourceFile);
            return true;
        }
        return false;
    }

    public function deleteDestinationFile(): bool
    {
        if ($this->error) {
            return false;
        }
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
            return true;
        }
        return false;
    }

    public function isDestinationDirectoryExists(): bool
    {
        $directoryPath = dirname($this->outputFile);
        if (!is_dir($directoryPath)) {
            $this->error = "Destination directory does not exists: " . $directoryPath;
            return false;
        }
        return true;
    }

    /**
     * @return false|string Returns the saved destination file path
     */
    public function toBase64File(): false|string
    {
        if ($this->error) {
            return false;
        }
        $fileContents = $this->readFileContents();
        if (!$fileContents) {
            return false;
        }
        if ($this->writeFileContents(base64_encode($fileContents))) {
            return $this->outputFile;
        }
        return false;
    }

    /**
     * @return false|string Returns the saved destination file path
     */
    public function fromBase64ToOrigin(): false|string
    {
        if ($this->error) {
            return false;
        }
        $fileContents = $this->readFileContents();
        if (!$fileContents) {
            return false;
        }
        if ($this->writeFileContents(base64_decode($fileContents))) {
            return $this->outputFile;
        }
        return false;
    }

    function getFileSize(string $filePath): string
    {
        $size = filesize($filePath); // Get the file size in bytes

        if ($size >= 1073741824) { // If the size is greater than or equal to 1 GB (1 GB = 1024 MB * 1024 KB * 1024 bytes)
            $sizeInGb = round($size / 1073741824, 2); // Convert to GB with 2 decimal places
            return $sizeInGb . ' GB';
        } elseif ($size >= 1048576) { // If the size is greater than or equal to 1 MB
            $sizeInMb = round($size / 1048576, 2); // Convert to MB with 2 decimal places
            return $sizeInMb . ' MB';
        } else { // If the size is less than 1 MB
            $sizeInKb = round($size / 1024, 2); // Convert to KB with 2 decimal places
            return $sizeInKb . ' KB';
        }
    }



    /**
     * @return string the file contents
     */
    public function readFileContents(): false|string
    {

        if ($this->error) {
            return false;
        }
        $fileContents = file_get_contents($this->sourceFile);
        if (!$fileContents) {
            $this->error = "Failed to read file $this->sourceFile";
            return false;
        }
        return $fileContents;
    }

    private function writeFileContents(string $contents): bool
    {
        if ($this->error) {
            return false;
        }
        if (!file_put_contents($this->outputFile, $contents)) {
            $this->error = "Failed to write file to destination $this->outputFile";
            return false;
        }
        return true;
    }
}
