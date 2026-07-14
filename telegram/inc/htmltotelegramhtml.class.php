<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Converts HTML notification content to Telegram-compatible HTML.
 * Telegram supports: <b>, <strong>, <i>, <em>, <u>, <s>, <a href="">, <code>, <pre>
 */
class PluginTelegramHtmlToTelegramHtml
{
    /**
     * Convert HTML to Telegram-compatible HTML
     */
    public static function convert($html)
    {
        if (empty($html)) {
            return '';
        }

        // Decode HTML entities
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove email reply markers
        $text = preg_replace('/=-=-=-=.*?=-=-=-=/s', '', $text);
        $text = preg_replace('/=_=_=_=.*?=_=_=_=/s', '', $text);
        
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

        // Process <br> tags
        $text = preg_replace('/<br\s*\/?>/is', "\n", $text);
        
        // Process links - convert to <a href="url">text</a>
        $text = self::processLinks($text);

        // Process images
        $text = self::processImages($text);

        // Process UNDERLINE + BOLD combined
        // <span style="text-decoration: underline;"><strong>text</strong></span> -> <u><b>text</b></u>
        $text = preg_replace(
            '/<span[^>]*style=["\'][^"\']*text-decoration:\s*underline[^"\']*["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/span>/is',
            '<u><b>$1</b></u>',
            $text
        );
        $text = preg_replace(
            '/<strong[^>]*>\s*<span[^>]*style=["\'][^"\']*text-decoration:\s*underline[^"\']*["\'][^>]*>(.*?)<\/span>\s*<\/strong>/is',
            '<u><b>$1</b></u>',
            $text
        );
        $text = preg_replace(
            '/<span[^>]*style=["\'][^"\']*text-decoration:\s*underline[^"\']*["\'][^>]*>\s*<b[^>]*>(.*?)<\/b>\s*<\/span>/is',
            '<u><b>$1</b></u>',
            $text
        );
        $text = preg_replace(
            '/<b[^>]*>\s*<span[^>]*style=["\'][^"\']*text-decoration:\s*underline[^"\']*["\'][^>]*>(.*?)<\/span>\s*<\/b>/is',
            '<u><b>$1</b></u>',
            $text
        );

        // Process UNDERLINE only
        $text = preg_replace(
            '/<span[^>]*style=["\'][^"\']*text-decoration:\s*underline[^"\']*["\'][^>]*>(.*?)<\/span>/is',
            '<u>$1</u>',
            $text
        );
        $text = preg_replace('/<u[^>]*>(.*?)<\/u>/is', '<u>$1</u>', $text);

        // Process BOLD (keep as <b> or <strong>)
        $text = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '<b>$1</b>', $text);
        $text = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '<b>$1</b>', $text);

        // Process ITALIC
        $text = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '<i>$1</i>', $text);
        $text = preg_replace('/<i[^>]*>(.*?)<\/i>/is', '<i>$1</i>', $text);

        // Process STRIKETHROUGH
        $text = preg_replace('/<s[^>]*>(.*?)<\/s>/is', '<s>$1</s>', $text);
        $text = preg_replace('/<strike[^>]*>(.*?)<\/strike>/is', '<s>$1</s>', $text);
        $text = preg_replace('/<del[^>]*>(.*?)<\/del>/is', '<s>$1</s>', $text);

        // Process HEADERS - convert to bold
        $text = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', "\n<b>$1</b>\n", $text);

        // Process CODE blocks
        $text = preg_replace('/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is', "\n<pre>$1</pre>\n", $text);
        $text = preg_replace('/<pre[^>]*>(.*?)<\/pre>/is', "\n<pre>$1</pre>\n", $text);
        $text = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '<code>$1</code>', $text);

        // Process BLOCKQUOTES
        $text = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n<i>$1</i>\n", $text);

        // Process LISTS
        $text = self::processLists($text);

        // Process HORIZONTAL RULES
        $text = preg_replace('/<hr\s*\/?>/is', "\n---\n", $text);

        // Process PARAGRAPHS
        $text = preg_replace('/<p[^>]*>/is', '', $text);
        $text = preg_replace('/<\/p>/is', "\n\n", $text);

        // Remove span and div tags (keep content)
        $text = preg_replace('/<\/?(span|div)[^>]*>/is', '', $text);

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/^[ \t]+/m', '', $text);
        $text = preg_replace('/[ \t]+$/m', '', $text);
        
        // Remove empty tags
        $text = preg_replace('/<([a-z]+)><\/\1>/i', '', $text);
        
        $text = trim($text);

        return $text;
    }

    /**
     * Process links
     */
    private static function processLinks($text)
    {
        // Process <a> tags
        $text = preg_replace_callback(
            '/<a[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/is',
            function($matches) {
                $url = trim($matches[1]);
                $linkText = trim(strip_tags($matches[2]));
                
                if (empty($url)) {
                    return $linkText;
                }
                
                if (empty($linkText)) {
                    $linkText = $url;
                }
                
                // Handle mailto:
                if (strpos($url, 'mailto:') === 0) {
                    $email = substr($url, 7);
                    if (strpos($email, '?') !== false) {
                        $email = substr($email, 0, strpos($email, '?'));
                    }
                    return "<a href=\"mailto:{$email}\">{$linkText}</a>";
                }
                
                // Handle http/https
                if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
                    return "<a href=\"{$url}\">{$linkText}</a>";
                }
                
                return "<a href=\"{$url}\">{$linkText}</a>";
            },
            $text
        );
        
        // Process plain URLs - convert to links
        $text = preg_replace_callback(
            '/(https?:\/\/[^\s<>"\']+)/i',
            function($matches) {
                $url = $matches[0];
                // Check if URL is already inside an <a> tag
                if (strpos($url, 'href=') !== false) {
                    return $url;
                }
                return "<a href=\"{$url}\">{$url}</a>";
            },
            $text
        );
        
        // Process plain emails
        $text = preg_replace_callback(
            '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
            function($matches) {
                $email = $matches[1];
                return "<a href=\"mailto:{$email}\">{$email}</a>";
            },
            $text
        );
        
        return $text;
    }

    /**
     * Process images
     */
    private static function processImages($text)
    {
        $text = preg_replace_callback(
            '/<img[^>]*src=["\'](.*?)["\'][^>]*alt=["\'](.*?)["\'][^>]*>/is',
            function($matches) {
                $url = trim($matches[1]);
                $alt = trim(strip_tags($matches[2]));
                return empty($alt) ? "<a href=\"{$url}\">Image</a>" : "<a href=\"{$url}\">{$alt}</a>";
            },
            $text
        );
        
        $text = preg_replace_callback(
            '/<img[^>]*src=["\'](.*?)["\'][^>]*>/is',
            function($matches) {
                return "<a href=\"{$matches[1]}\">Image</a>";
            },
            $text
        );
        
        return $text;
    }

    /**
     * Process lists
     */
    private static function processLists($text)
    {
        $text = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is', function($matches) {
            return self::convertUnorderedList($matches[1]);
        }, $text);
        
        $text = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) {
            return self::convertOrderedList($matches[1]);
        }, $text);
        
        return $text;
    }

    /**
     * Convert unordered list
     */
    private static function convertUnorderedList($content, $depth = 0)
    {
        $result = '';
        $indent = str_repeat('  ', $depth);
        
        $items = preg_split('/<\/li>\s*<li[^>]*>/i', $content);
        
        foreach ($items as $item) {
            $item = preg_replace('/^<li[^>]*>/i', '', $item);
            $item = preg_replace('/<\/li>$/i', '', $item);
            
            if (preg_match('/<(ul|ol)[^>]*>/i', $item)) {
                $parts = preg_split('/<(ul|ol)[^>]*>/i', $item, 2);
                $text = trim(strip_tags($parts[0]));
                
                if (!empty($text)) {
                    $result .= $indent . '• ' . $text . "\n";
                }
                
                $nestedContent = $parts[1];
                if (preg_match('/<ul/i', $item)) {
                    $result .= self::convertUnorderedList($nestedContent, $depth + 1);
                } else {
                    $result .= self::convertOrderedList($nestedContent, $depth + 1);
                }
            } else {
                $text = trim(strip_tags($item));
                if (!empty($text)) {
                    $result .= $indent . '• ' . $text . "\n";
                }
            }
        }
        
        return $result;
    }

    /**
     * Convert ordered list
     */
    private static function convertOrderedList($content, $depth = 0, $startNumber = 1)
    {
        $result = '';
        $indent = str_repeat('  ', $depth);
        $counter = $startNumber;
        
        $items = preg_split('/<\/li>\s*<li[^>]*>/i', $content);
        
        foreach ($items as $item) {
            $item = preg_replace('/^<li[^>]*>/i', '', $item);
            $item = preg_replace('/<\/li>$/i', '', $item);
            
            if (preg_match('/<(ul|ol)[^>]*>/i', $item)) {
                $parts = preg_split('/<(ul|ol)[^>]*>/i', $item, 2);
                $text = trim(strip_tags($parts[0]));
                
                if (!empty($text)) {
                    $result .= $indent . $counter . '. ' . $text . "\n";
                }
                
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