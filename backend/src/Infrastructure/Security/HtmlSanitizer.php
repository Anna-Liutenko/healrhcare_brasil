<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Server-side HTML Sanitizer
 *
 * Defense-in-depth: validates HTML even if client-side DOMPurify bypassed.
 * Uses HTMLPurifier for robust sanitization.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    /**
     * Get HTMLPurifier instance with secure config
     *
     * @return HTMLPurifier
     */
    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            
            // Allow basic HTML elements
            $config->set('HTML.Allowed', 'p,b,strong,i,em,u,a[href],img,ul,ol,li,br,h1,h2,h3,h4,h5,h6,blockquote,code,pre');
            // Allow specific attributes
            $config->set('HTML.AllowedAttributes', 'a.href,a.title,a.target,img.src,img.alt,img.title');
            
            // Disable external resources
            $config->set('URI.DisableExternalResources', true);
            $config->set('URI.DisableResources', true);
            
            // Remove all JavaScript
            $config->set('HTML.ForbiddenElements', ['script', 'iframe', 'object', 'embed', 'applet', 'base', 'meta', 'link']);
            $config->set('HTML.ForbiddenAttributes', ['onclick', 'onerror', 'onload', 'onmouseover', 'onmouseout', 'onkeydown', 'onkeyup', 'onkeypress']);
            
            // Cache config for performance
            $config->set('Cache.DefinitionImpl', null);
            
            self::$purifier = new HTMLPurifier($config);
        }
        
        return self::$purifier;
    }

    /**
     * Sanitize HTML using HTMLPurifier
     *
     * @param string $html
     * @return string
     */
    public static function sanitize(string $html): string
    {
        return @self::getPurifier()->purify($html);
    }

    /**
     * Validate HTML safety (returns array of found violations)
     *
     * Compares input vs purified output to detect violations.
     *
     * @param string $html
     * @return array
     */
    public static function validate(string $html): array
    {
        $violations = [];
        $purified = self::sanitize($html);
        
        // Check for dangerous tags removed
        if (preg_match('/<(script|iframe|object|embed|applet)/i', $html) && !preg_match('/<(script|iframe|object|embed|applet)/i', $purified)) {
            $violations[] = 'Dangerous tag removed during sanitization';
        }
        
        // Check for event handlers removed
        if (preg_match('/\son\w+\s*=/i', $html) && !preg_match('/\son\w+\s*=/i', $purified)) {
            $violations[] = 'Event handler attribute removed during sanitization';
        }
        
        // Check for javascript URLs removed
        if (preg_match('/javascript:/i', $html) && !preg_match('/javascript:/i', $purified)) {
            $violations[] = 'javascript: URL removed during sanitization';
        }
        
        // Check for data URLs removed
        if (preg_match('/data:text\/html/i', $html) && !preg_match('/data:text\/html/i', $purified)) {
            $violations[] = 'data:text/html URL removed during sanitization';
        }
        
        return $violations;
    }
}
