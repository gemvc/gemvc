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
        // Calculate the longest line length using mb_strwidth for proper display width (handles emojis)
        $maxLength = 0;
        foreach ($lines as $line) {
            // Remove ANSI color codes for length calculation
            $cleanLine = preg_replace('/\033\[[0-9;]*m/', '', $line) ?? $line;
            $maxLength = max($maxLength, mb_strwidth($cleanLine, 'UTF-8'));
        }
        
        // Calculate title length for proper width calculation (using mb_strwidth for emoji support)
        $titleLength = mb_strwidth($title, 'UTF-8');
        
        // Ensure minimum width and add padding
        $boxWidth = max(50, max($maxLength, $titleLength + 10) + 4);
        
        // Check ANSI color support
        $supportsAnsi = $this->supportsAnsiColors();
        
        // Create the top border - echo directly to preserve color codes
        // Top border: ╭ (1) + ─ (1) + space (1) + title + space (1) + dashes + ╮ (1) = boxWidth
        // Dashes = boxWidth - 1 - 1 - 1 - titleLength - 1 - 1 = boxWidth - titleLength - 5
        $dashCount = $boxWidth - $titleLength - 5;
        if ($dashCount < 0) {
            $dashCount = 0;
        }
        // Yellow color code for borders - use both bright and regular yellow for compatibility
        $yellowColor = "\033[1;33m";
        $yellowColorAlt = "\033[33m"; // Regular yellow as fallback
        $resetColor = "\033[0m";
        
        // Strip any existing color codes from title and make it yellow to match border
        $cleanTitle = preg_replace('/\033\[[0-9;]*m/', '', $title) ?? $title;
        
        // Always apply yellow color to borders (assume ANSI support if other colors work)
        // Entire border line is yellow, including title
        echo "\n{$yellowColor}╭─ {$cleanTitle} " . str_repeat('─', $dashCount) . "╮{$resetColor}\n";
        
        // Create content lines - echo directly to preserve embedded color codes
        foreach ($lines as $line) {
            // Remove ANSI codes to get clean line length
            $cleanLine = preg_replace('/\033\[[0-9;]*m/', '', $line) ?? $line;
            // Use mb_strwidth to get proper display width (accounts for emojis that are 2 chars wide)
            $cleanLength = mb_strwidth($cleanLine, 'UTF-8');
            
            // Calculate padding: boxWidth - left border (1) - space (1) - content - right border (1)
            // But boxWidth already accounts for borders, so: boxWidth - 1 (left border) - 1 (space) - content length - 1 (right border)
            $padding = $boxWidth - $cleanLength - 3; // -3 for: left border (│), space, right border (│)
            
            // Ensure padding is at least 0
            if ($padding < 0) {
                $padding = 0;
            }
            
            // Build the line: left border (yellow) + space + content + padding + right border (yellow)
            // Always apply yellow to borders
            echo "{$yellowColor}│{$resetColor} {$line}" . str_repeat(' ', $padding) . "{$yellowColor}│{$resetColor}\n";
        }
        
        // Create the bottom border - echo directly to preserve color codes
        // Bottom border: ╰ (1) + dashes + ╯ (1) = boxWidth
        // Dashes = boxWidth - 2
        $bottomDashCount = $boxWidth - 2;
        if ($bottomDashCount < 0) {
            $bottomDashCount = 0;
        }
        // Always apply yellow to bottom border
        echo "{$yellowColor}╰" . str_repeat('─', $bottomDashCount) . "╯{$resetColor}\n";
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