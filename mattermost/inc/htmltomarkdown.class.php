<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Converts HTML notification content to Mattermost-compatible Markdown.
 */
class PluginMattermostHtmlToMarkdown
{
    /**
     * Convert HTML string to Markdown for Mattermost
     */
    public static function convert($html)
    {
        if (empty($html)) {
            return '';
        }

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove HTML wrappers
        $text = preg_replace('/<!DOCTYPE[^>]*>/i', '', $text);
        $text = preg_replace('/<html[^>]*>/i', '', $text);
        $text = preg_replace('/<\/html>/i', '', $text);
        $text = preg_replace('/<head>.*?<\/head>/is', '', $text);
        $text = preg_replace('/<body[^>]*>/i', '', $text);
        $text = preg_replace('/<\/body>/i', '', $text);
        $text = preg_replace('/<meta[^>]*>/i', '', $text);
        $text = preg_replace('/<title>.*?<\/title>/is', '', $text);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);

        // IMPORTANT: Process <br> tags BEFORE other transformations
        // to prevent breaking markdown formatting
        $text = preg_replace('/<br\s*\/?>/is', "\n", $text);
        
        // Headers - Mattermost supports h1-h6
        $text = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "\n# $1\n", $text);
        $text = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n## $1\n", $text);
        $text = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "\n### $1\n", $text);
        $text = preg_replace('/<h4[^>]*>(.*?)<\/h4>/is', "\n#### $1\n", $text);
        $text = preg_replace('/<h5[^>]*>(.*?)<\/h5>/is', "\n##### $1\n", $text);
        $text = preg_replace('/<h6[^>]*>(.*?)<\/h6>/is', "\n###### $1\n", $text);

        // Bold + Italic combined (process before individual bold/italic)
        $text = preg_replace('/<strong[^>]*><em[^>]*>(.*?)<\/em><\/strong>/is', '***$1***', $text);
        $text = preg_replace('/<em[^>]*><strong[^>]*>(.*?)<\/strong><\/em>/is', '***$1***', $text);
        $text = preg_replace('/<b[^>]*><i[^>]*>(.*?)<\/i><\/b>/is', '***$1***', $text);
        $text = preg_replace('/<i[^>]*><b[^>]*>(.*?)<\/b><\/i>/is', '***$1***', $text);

        // Bold
        $text = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $text);
        $text = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '**$1**', $text);

        // Italic
        $text = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $text);
        $text = preg_replace('/<i[^>]*>(.*?)<\/i>/is', '*$1*', $text);

        // Strikethrough
        $text = preg_replace('/<s[^>]*>(.*?)<\/s>/is', '~~$1~~', $text);
        $text = preg_replace('/<strike[^>]*>(.*?)<\/strike>/is', '~~$1~~', $text);
        $text = preg_replace('/<del[^>]*>(.*?)<\/del>/is', '~~$1~~', $text);

        // Underline - Mattermost supports <u> tags natively
        $text = preg_replace('/<u[^>]*>(.*?)<\/u>/is', '<u>$1</u>', $text);

        // Links - must process before paragraphs
        $text = preg_replace('/<a[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text);
        
        // Images
        $text = preg_replace('/<img[^>]*src=["\'](.*?)["\'][^>]*alt=["\'](.*?)["\'][^>]*>/is', '![$2]($1)', $text);
        $text = preg_replace('/<img[^>]*src=["\'](.*?)["\'][^>]*>/is', '![]($1)', $text);

        // Lists processing
        $text = self::processLists($text);

        // Paragraphs - simplified to avoid breaking formatting
        $text = preg_replace('/<p[^>]*>/is', '', $text);
        $text = preg_replace('/<\/p>/is', "\n\n", $text);
        
        // Horizontal rules
        $text = preg_replace('/<hr\s*\/?>/is', "\n---\n", $text);

        // Blockquotes
        $text = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n", $text);

        // Code blocks
        $text = preg_replace('/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is', "\n```\n$1\n```\n", $text);
        $text = preg_replace('/<pre[^>]*>(.*?)<\/pre>/is', "\n```\n$1\n```\n", $text);
        $text = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '`$1`', $text);

        // Tables (basic support)
        $text = preg_replace('/<table[^>]*>/is', "\n", $text);
        $text = preg_replace('/<\/table>/is', "\n", $text);
        $text = preg_replace('/<tr[^>]*>/is', '', $text);
        $text = preg_replace('/<\/tr>/is', "\n", $text);
        $text = preg_replace('/<td[^>]*>(.*?)<\/td>/is', '| $1 ', $text);
        $text = preg_replace('/<th[^>]*>(.*?)<\/th>/is', '| **$1** ', $text);

        // Remove span and div tags while preserving content
        $text = preg_replace('/<\/?(span|div)[^>]*>/is', '', $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);
        
        // Clean up whitespace and formatting
        $text = preg_replace('/[ \t]+/', ' ', $text);  // Multiple spaces to single space
        $text = preg_replace('/\n{3,}/', "\n\n", $text);  // Max 2 consecutive newlines
        $text = preg_replace('/^[ \t]+/m', '', $text);  // Remove leading spaces
        $text = preg_replace('/[ \t]+$/m', '', $text);  // Remove trailing spaces
        
        // Fix markdown formatting edge cases
        $text = preg_replace('/\*\*([^*\n]+)\*\*/', '**$1**', $text);  // Clean bold
        $text = preg_replace('/\*([^*\n]+)\*/', '*$1*', $text);  // Clean italic
        $text = preg_replace('/\n\s*######/', "\n######", $text);  // Fix h6 spacing
        
        $text = trim($text);

        return $text;
    }

    /**
     * Process both ordered and unordered lists with nesting support
     */
    private static function processLists($text)
    {
        // Handle unordered lists
        $text = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is', function($matches) {
            return self::convertUnorderedList($matches[1]);
        }, $text);
        
        // Handle ordered lists
        $text = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) {
            return self::convertOrderedList($matches[1]);
        }, $text);
        
        return $text;
    }

    /**
     * Convert unordered list items to markdown
     */
    private static function convertUnorderedList($content, $depth = 0)
    {
        $result = '';
        $indent = str_repeat('  ', $depth);
        
        // Split by list items
        $items = preg_split('/<\/li>\s*<li[^>]*>/i', $content);
        
        foreach ($items as $item) {
            $item = preg_replace('/^<li[^>]*>/i', '', $item);
            $item = preg_replace('/<\/li>$/i', '', $item);
            
            // Check for nested lists
            if (preg_match('/<(ul|ol)[^>]*>/i', $item)) {
                // Extract text before nested list
                $parts = preg_split('/<(ul|ol)[^>]*>/i', $item, 2);
                $text = trim(strip_tags($parts[0]));
                
                $result .= $indent . '- ' . $text . "\n";
                
                // Process nested list
                $nestedContent = $parts[1];
                if (preg_match('/<ul/i', $item)) {
                    $result .= self::convertUnorderedList($nestedContent, $depth + 1);
                } else {
                    $result .= self::convertOrderedList($nestedContent, $depth + 1);
                }
            } else {
                $text = trim(strip_tags($item));
                if (!empty($text)) {
                    $result .= $indent . '- ' . $text . "\n";
                }
            }
        }
        
        return $result;
    }

    /**
     * Convert ordered list items to markdown
     */
    private static function convertOrderedList($content, $depth = 0, $startNumber = 1)
    {
        $result = '';
        $indent = str_repeat('  ', $depth);
        $counter = $startNumber;
        
        // Split by list items
        $items = preg_split('/<\/li>\s*<li[^>]*>/i', $content);
        
        foreach ($items as $item) {
            $item = preg_replace('/^<li[^>]*>/i', '', $item);
            $item = preg_replace('/<\/li>$/i', '', $item);
            
            // Check for nested lists
            if (preg_match('/<(ul|ol)[^>]*>/i', $item)) {
                // Extract text before nested list
                $parts = preg_split('/<(ul|ol)[^>]*>/i', $item, 2);
                $text = trim(strip_tags($parts[0]));
                
                $result .= $indent . $counter . '. ' . $text . "\n";
                
                // Process nested list
                $nestedContent = $parts[1];
                if (preg_match('/<ul/i', $item)) {
                    $result .= self::convertUnorderedList($nestedContent, $depth + 1);
                } else {
                    $result .= self::convertOrderedList($nestedContent, $depth + 1);
                }
            } else {
                $text = trim(strip_tags($item));
                if (!empty($text)) {
                    $result .= $indent . $counter . '. ' . $text . "\n";
                }
            }
            $counter++;
        }
        
        return $result;
    }
}
