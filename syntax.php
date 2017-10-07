<?php
/**
 * DokuWiki Plugin gh (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_gh extends DokuWiki_Syntax_Plugin {

    /**
     * Extension to highlighting language mapping
     *
     * When a extenison is not found here it's assumed the extension name equals the language
     *
     * @var array
     */
    protected $ext2lang = array(
        'as'   => 'actionscript3',
        'sh'   => 'bash',
        'bas'  => 'gwbasic',
        'hs'   => 'haskell',
        'htm'  => 'html5',
        'html' => 'html5',
        'js'   => 'javascript',
        'pas'  => 'pascal',
        'pl'   => 'perl6',
        'rb'   => 'ruby',
        'py'   => 'python',
    );

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 155;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{gh>[^}]*}}', $mode, 'plugin_gh');

    }

    /**
     * Handle matches of the gh syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $match = trim(substr($match, 5, -2));
        list($url, $lines) = explode(' ', $match, 2);
        list($from, $to) = explode('-', $lines, 2);

        $data = array(
            'from' => (int) $from,
            'to'   => (int) $to
        );

        if(preg_match('/github\.com\/([\w-]+)\/([\w-]+)\/blob\/([\w-]+)\/(.*)$/', $url, $m)) {
            $data['user'] = $m[1];
            $data['repo'] = $m[2];
            $data['blob'] = $m[3];
            $data['file'] = $m[4];
        }

        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer  The renderer
     * @param array         $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml') return false;

        if(!$data['user']) return false;
        if(!$data['repo']) return false;
        if(!$data['blob']) return false;
        if(!$data['file']) return false;

        global $ID;
        global $INPUT;

        $raw = 'https://raw.githubusercontent.com/'.$data['user'].'/'.$data['repo'].'/'.$data['blob'].'/'.$data['file'];
        $url = 'https://github.com/'.$data['user'].'/'.$data['repo'].'/blob/'.$data['blob'].'/'.$data['file'];

        // check if there's a usable cache
        $text   = false;
        $cache  = getCacheName($raw, '.ghplugin');
        $tcache = @filemtime($cache);
        $tpage  = @filemtime(wikiFN($ID));
        if($tcache && $tpage && !$INPUT->bool('purge')) {
            $now = time();
            if($now - $tcache < ($now - $tpage) * 2) {
                // use cache when it's younger than twice the age of the page
                $text = io_readFile($cache);
            }
        }

        // no cache loaded, get from HTTP
        if(!$text) {
            $http = new DokuHTTPClient();
            $text = $http->get($raw);

            if($text) {
                // save to cache
                io_saveFile($cache, $text);
            } else if($tcache) {
                // HTTP failed but there's an old cache - use it
                $text = io_readFile($cache);
            }
        }

        // WTF? there's nothing. we're done here
        if(!$text) return true;

        // apply line ranges
        if($data['from'] || $data['to']) {
            $len = $data['to'] - $data['from'];
            if($len <= 0) $len = null;

            $lines = explode("\n", $text);
            $lines = array_slice($lines, $data['from'], $len);
            $text  = join("\n", $lines);
        }

        // add icon
        list($ext) = mimetype($data['file'], false);
        $class = preg_replace('/[^_\-a-z0-9]+/i', '_', $ext);
        $class = 'mediafile mf_'.$class;

        // add link
        $renderer->doc .= '<dl class="file">'.DOKU_LF;
        $renderer->doc .= '<dt><a href="'.$url.'" class="'.$class.'">';
        $renderer->doc .= hsc($data['file']);
        $renderer->doc .= '</a></dt>'.DOKU_LF.'<dd>';

        if(isset($this->ext2lang[$ext])) {
            $lang = $this->ext2lang[$ext];
        } else {
            $lang = $ext;
        }

        $renderer->file($text, $lang);
        $renderer->doc .= '</dd>';
        $renderer->doc .= '</dl>';
        return true;
    }
}

// vim:ts=4:sw=4:et:
