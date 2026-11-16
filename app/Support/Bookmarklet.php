<?php

namespace App\Support;

/**
 * The bookmark button that sends the listing open in the appraiser's browser
 * to the system: its address, title, visible text, structured data and
 * og: tags, packed after # of the capture page (never sent in the request
 * line, read by admin.js there; "#" is written as a character code, as some
 * browsers cut a bookmark address at it). Sites that refuse the server show the
 * listing to the person, so this works wherever the listing opens.
 */
final class Bookmarklet
{
    public static function analog(string $captureUrl): string
    {
        $script = <<<'JS'
            (function(){var q=function(s){return Array.prototype.slice.call(document.querySelectorAll(s))};
            var m={};q('meta[property],meta[name]').forEach(function(e){var k=(e.getAttribute('property')||e.getAttribute('name')||'').toLowerCase();if(/^(og:|product:|description$)/.test(k)&&!(k in m))m[k]=(e.getAttribute('content')||'').slice(0,1000)});
            var d={u:location.href,t:document.title,x:((document.body&&document.body.innerText)||'').slice(0,20000),l:q('script[type="application/ld+json"]').slice(0,5).map(function(s){return s.textContent.slice(0,20000)}),m:m};
            var u=CAPTURE+String.fromCharCode(35)+btoa(unescape(encodeURIComponent(JSON.stringify(d))));
            if(!window.open(u,'_blank'))location.href=u})()
            JS;

        $script = preg_replace('/\n\s*/', '', $script);

        return 'javascript:'.str_replace('CAPTURE', json_encode($captureUrl, JSON_UNESCAPED_SLASHES), $script);
    }
}
