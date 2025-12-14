<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;

/**
 * CLI Box Display Utility
 * 
 * Provides reusable methods for creating consistent, aligned ASCII art boxes
 * in CLI applications. Handles dynamic width calculation, color support,
 * and proper alignment.
 */
class CliBoxShow extends Command
{
    /**
     * Display a dynamic box with automatic width calculation
     * 
     * @param array<string> $lines
     */
    public function displayBox(string $title, array $lines, string $color = 'yellow'): void
    {
        // Calculate the longest line length
        $maxLength = 0;
        foreach ($lines as $line) {
            // Remove ANSI color codes for length calculation
            $cleanLine = preg_replace('/\033\[[0-9;]*m/', '', $line) ?? $line;
            $maxLength = max($maxLength, strlen($cleanLine));
        }
        
        // Calculate title length for proper width calculation
        $titleLength = strlen($title);
        
        // Ensure minimum width and add padding
        $boxWidth = max(50, max($maxLength, $titleLength + 10) + 4);
        
        // Create the top border
        $titleLine = "╭─ {$title} " . str_repeat('─', $boxWidth - $titleLength - 4) . "╮";
        $this->write("\n\033[1;33m{$titleLine}\033[0m\n", $color);
        
        // Create content lines
        foreach ($lines as $line) {
            $cleanLine = preg_replace('/\033\[[0-9;]*m/', '', $line) ?? $line;
            $padding = $boxWidth - strlen($cleanLine) - 2;
            $paddedLine = "│ {$line}" . str_repeat(' ', $padding) . "│";
            $this->write("\033[1;33m{$paddedLine}\033[0m\n", 'white');
        }
        
        // Create the bottom border
        $bottomLine = "╰" . str_repeat('─', $boxWidth) . "╯";
        $this->write("\033[1;33m{$bottomLine}\033[0m\n", $color);
    }
    
    /**
     * Display a success box with green styling
     */
    /**
     * @param array<string> $lines
     */
    public function displaySuccessBox(string $title, array $lines): void
    {
        $this->displayBox($title, $lines, 'green');
    }
    
    /**
     * Display an info box with blue styling
     */
    /**
     * @param array<string> $lines
     */
    public function displayInfoBox(string $title, array $lines): void
    {
        $this->displayBox($title, $lines, 'blue');
    }
    
    /**
     * Display a warning box with yellow styling
     */
    /**
     * @param array<string> $lines
     */
    public function displayWarningBox(string $title, array $lines): void
    {
        $this->displayBox($title, $lines, 'yellow');
    }
    
    /**
     * Display an error box with red styling
     */
    /**
     * @param array<string> $lines
     */
    public function displayErrorBox(string $title, array $lines): void
    {
        $this->displayBox($title, $lines, 'red');
    }
    
    /**
     * Display a simple message box without title
     */
    /**
     * @param array<string> $lines
     */
    public function displayMessageBox(array $lines, string $color = 'yellow'): void
    {
        $this->displayBox('', $lines, $color);
    }
    
    /**
     * Display a tool installation prompt box
     */
    public function displayToolInstallationPrompt(string $title, string $question, string $description, string $additionalInfo = ''): void
    {
        $lines = [
            $question,
            $description
        ];
        
        if ($additionalInfo) {
            $lines[] = $additionalInfo;
        }
        
        $this->displayBox($title, $lines);
    }
    
    /**
     * Required execute method (not used in utility class)
     */
    public function execute(): bool
    {
        // This is a utility class, not a command
        $this->error("CliBoxShow is a utility class and should not be executed directly.");
        return false;
    }
}