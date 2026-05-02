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

        // Headers
        $text = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "\n# $1\n", $text);
        $text = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n## $1\n", $text);
        $text = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "\n### $1\n", $text);
        $text = preg_replace('/<h4[^>]*>(.*?)<\/h4>/is', "\n#### $1\n", $text);

        // Bold and italic
        $text = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $text);
        $text = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '**$1**', $text);
        $text = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $text);
        $text = preg_replace('/<i[^>]*>(.*?)<\/i>/is', '*$1*', $text);

        // Links
        $text = preg_replace('/<a[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text);

        // Lists
        $text = preg_replace('/<ul[^>]*>/is', '', $text);
        $text = preg_replace('/<\/ul>/is', "\n", $text);
        $text = preg_replace('/<ol[^>]*>/is', '', $text);
        $text = preg_replace('/<\/ol>/is', "\n", $text);
        $text = preg_replace('/<li[^>]*>(.*?)<\/li>/is', '- $1', $text);

        // Paragraphs and breaks
        $text = preg_replace('/<p[^>]*>/is', '', $text);
        $text = preg_replace('/<\/p>/is', "\n\n", $text);
        $text = preg_replace('/<br\s*\/?>/is', "\n", $text);
        $text = preg_replace('/<hr\s*\/?>/is', "\n---\n", $text);

        // Code
        $text = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '`$1`', $text);
        $text = preg_replace('/<pre[^>]*>(.*?)<\/pre>/is', "\n```\n$1\n```\n", $text);

        // Strip remaining tags and clean whitespace
        $text = strip_tags($text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = trim($text);

        return $text;
    }
}