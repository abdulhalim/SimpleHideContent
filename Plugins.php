<?php
/**
 * نمایش محتوای پنهان پس از ورود/پاسخ
 *
 * @package SimpleHideContent
 * @author پوردریایی
 * @version 1.3.1
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class SimpleHideContent_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->content = array('SimpleHideContent_Plugin', 'handleContent');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('SimpleHideContent_Plugin', 'handleExcerpt');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('SimpleHideContent_Plugin', 'handleExcerpt');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('SimpleHideContent_Plugin', 'injectFooterScript');
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('SimpleHideContent_Plugin', 'renderEditor');
        return _t('افزونه فعال شد.');
    }

    public static function deactivate() {}
    public static function config(Typecho_Widget_Helper_Form $form) {}
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    public static function renderEditor()
    {
        $pluginUrl = Helper::options()->pluginUrl . '/SimpleHideContent';
        echo '<script src="' . $pluginUrl . '/assets/editor.js"></script>';
    }

    private static function isAdminOrContributor()
    {
        $user = Typecho_Widget::widget('Widget_User');
        return $user->hasLogin() && ($user->pass('administrator', true) || $user->pass('contributor', true));
    }

    private static function checkCommented($cid, $mail = '', $userId = 0)
    {
        if (empty($mail) && $userId == 0) return false;
        try {
            $db = Typecho_Db::get();
            $query = $db->select()->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
                ->limit(1);
            $orParts = []; $params = [];
            if ($userId > 0) { $orParts[] = 'authorId = ?'; $params[] = $userId; }
            if (!empty($mail)) { $orParts[] = 'mail = ?'; $params[] = $mail; }
            if (!empty($orParts)) {
                $query->where('(' . implode(' OR ', $orParts) . ')', ...$params);
            }
            return (bool) $db->fetchRow($query);
        } catch (Exception $e) {
            return false;
        }
    }

    private static function getSvgLock()
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
    }

    private static function getSvgComment()
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
    }

    /**
     * تبدیل ساده Markdown به HTML (فقط برای تصاویر و خطوط جدید)
     */
    private static function simpleMarkdown($text)
    {
        // تبدیل تصاویر ![](url) به <img>
        $text = preg_replace_callback('/!\[(.*?)\]\((.*?)\)/', function($matches) {
            $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<img src="' . $url . '" alt="' . $alt . '" style="max-width:100%;">';
        }, $text);
        
        // تبدیل لینک‌های ساده [text](url) به <a>
        $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function($matches) {
            $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '" target="_blank" rel="noopener">' . $text . '</a>';
        }, $text);
        
        // تبدیل خطوط جدید به <br> و پاراگراف‌ها
        $paragraphs = explode("\n\n", $text);
        $result = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;
            // اگر پاراگراف شامل تگ HTML نباشد، در <p> بپیچ
            if (strip_tags($para) == $para) {
                $result .= '<p>' . nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8')) . '</p>';
            } else {
                $result .= '<p>' . $para . '</p>';
            }
        }
        return $result;
    }

    public static function handleContent($content, $widget)
    {
        if ($widget->is('single')) {
            $cid = $widget->cid;
            $user = Typecho_Widget::widget('Widget_User');
            $userId = $user->hasLogin() ? $user->uid : 0;
            $mail = $widget->remember('mail', true);
            $login = $user->hasLogin();
            $content = self::parse_content($content, $cid, $mail, $login, $userId);
        }
        return $content;
    }

    public static function handleExcerpt($con, $obj, $text)
    {
        $text = empty($text) ? $con : $text;
        if (!$obj->is('single')) {
            $cid = $obj->cid;
            $user = Typecho_Widget::widget('Widget_User');
            $userId = $user->hasLogin() ? $user->uid : 0;
            $mail = $obj->remember('mail', true);
            $login = $user->hasLogin();
            $hasCommented = self::checkCommented($cid, $mail, $userId);
            
            if ($hasCommented || self::isAdminOrContributor()) {
                $text = preg_replace_callback("/\[hide\](.*?)\[\/hide\]/sm", function($m) {
                    return self::simpleMarkdown($m[1]);
                }, $text);
            } else {
                $text = preg_replace("/\[hide\](.*?)\[\/hide\]/sm", '<span style="display:inline-flex; align-items:center; gap:6px;">' . self::getSvgComment() . ' محتوای این قسمت پنهان شده است</span>', $text);
            }
            
            if ($login) {
                $text = preg_replace_callback("/\[login\](.*?)\[\/login\]/sm", function($m) {
                    return self::simpleMarkdown($m[1]);
                }, $text);
            } else {
                $text = preg_replace("/\[login\](.*?)\[\/login\]/sm", '<span style="display:inline-flex; align-items:center; gap:6px;">' . self::getSvgLock() . ' برای نمایش محتوای پنهان شده باید وارد شوید.</span>', $text);
            }
        }
        return $text;
    }

    public static function injectFooterScript($archive)
    {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("comment_show");l&&l.addEventListener("click",function(e){e.preventDefault();var c=document.getElementById("comments");c&&(c.style.display="block",c.scrollIntoView({behavior:"smooth"}));});});</script>';
    }

    public static function parse_content($content, $cid, $mail, $login, $userId = 0)
    {
        $hasCommented = self::checkCommented($cid, $mail, $userId);
        $boxStyle = 'border:1px solid #e2e8f0; border-radius:12px; background:#fafafa; padding:16px 20px; margin:20px 0; text-align:center; color:#4a5568; display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap;';
        $linkStyle = 'color:#3182ce; text-decoration:none; font-weight:600; margin:0 4px;';
        $answerStyle = '<div style="' . $boxStyle . '">' . self::getSvgComment() . '<span>برای نمایش محتوای پنهان شده باید <a id="comment_show" href="#comments" style="' . $linkStyle . '">پاسخ دهید</a>.</span></div>';
        $loginStyle = '<div style="' . $boxStyle . '">' . self::getSvgLock() . '<span>برای نمایش محتوای پنهان شده باید وارد شوید.</span></div>';

        if ($hasCommented || self::isAdminOrContributor()) {
            $content = preg_replace_callback("/\[hide\](.*?)\[\/hide\]/sm", function($matches) {
                return self::simpleMarkdown($matches[1]);
            }, $content);
        } else {
            $content = preg_replace("/\[hide\](.*?)\[\/hide\]/sm", $answerStyle, $content);
        }

        if ($login) {
            $content = preg_replace_callback("/\[login\](.*?)\[\/login\]/sm", function($matches) {
                return self::simpleMarkdown($matches[1]);
            }, $content);
        } else {
            $content = preg_replace("/\[login\](.*?)\[\/login\]/sm", $loginStyle, $content);
        }

        return $content;
    }
}
