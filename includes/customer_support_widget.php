<?php
/**
 * Floating customer support button + modal (Support / Concerns / FAQ).
 *
 * Optional variables before include:
 *   $customerservice_img — relative URL; resolved automatically if unset
 *   $cs_contact_href     — e.g. '#contact' (index) or 'index.php#contact'
 *   $cs_announcement_href — non-empty string shows FAQ link (e.g. '#announcement' or 'index.php#announcement')
 *   $cs_concern_action — POST endpoint for concerns form (default: submit_customer_concern.php)
 *   $cs_google_account_url — optional; “Create an account” / step 1 link target (Google)
 *   $cs_gmail_steps_url_prefix — optional folder URL for the four step JPGs. If unset, paths are auto-detected
 *     (project root or assets/imgs/ when all four files exist there).
 *   $cs_show_only_below_width — if set (e.g. 991), hide the float button on wider viewports (mobile-only).
 *   $cs_show_menu_tools — if true, show calendar + language row in Support tab (menu.php).
 */
if (defined('DPM_CUSTOMER_SUPPORT_LOADED')) {
    return;
}
define('DPM_CUSTOMER_SUPPORT_LOADED', true);

$__dpm_root = dirname(__DIR__);

if (!isset($customerservice_img)) {
    $customerservice_img = 'customerservice.jpg';
    if (is_file($__dpm_root . DIRECTORY_SEPARATOR . 'customerservice.jpg')) {
        $customerservice_img = 'customerservice.jpg';
    } elseif (is_file($__dpm_root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imgs' . DIRECTORY_SEPARATOR . 'customerservice.jpg')) {
        $customerservice_img = 'assets/imgs/customerservice.jpg';
    }
}

$cs_contact_href = isset($cs_contact_href) ? (string) $cs_contact_href : '#contact';
$cs_announcement_href = isset($cs_announcement_href) ? trim((string) $cs_announcement_href) : '';
$cs_img_esc = htmlspecialchars($customerservice_img, ENT_QUOTES, 'UTF-8');
$cs_contact_esc = htmlspecialchars($cs_contact_href, ENT_QUOTES, 'UTF-8');
$cs_concern_action = isset($cs_concern_action) ? trim((string) $cs_concern_action) : 'submit_customer_concern.php';
if (!isset($cs_google_account_url)) {
    $cs_google_account_url = 'https://accounts.google.com/v3/signin/identifier?dsh=S-1792839171%3A1775407555127872&flowName=GlifWebSignIn&flowEntry=ServiceLogin&ifkv=AT1y2_XwRqaSHkc7EXdmH6WBi1RmuRwemLBH-aBbCq3LnNcnWAPB1_lmoa8CR4hywWFFQv7T7DMX';
}
$cs_google_account_url_esc = htmlspecialchars((string) $cs_google_account_url, ENT_QUOTES, 'UTF-8');

$cs_gmail_step_files = ['firststep.jpg', 'secondstep.jpg', 'thirdstep.jpg', 'fourthstep.jpg'];
$cs_gmail_step_base = '';
if (isset($cs_gmail_steps_url_prefix) && trim((string) $cs_gmail_steps_url_prefix) !== '') {
    $cs_gmail_step_base = rtrim(trim((string) $cs_gmail_steps_url_prefix), '/') . '/';
} elseif (!isset($cs_gmail_steps_url_prefix)) {
    $cs_gmail_all_in_dir = function ($relative_dir) use ($__dpm_root, $cs_gmail_step_files) {
        foreach ($cs_gmail_step_files as $gf) {
            $path = ($relative_dir === '')
                ? ($__dpm_root . DIRECTORY_SEPARATOR . $gf)
                : ($__dpm_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_dir) . DIRECTORY_SEPARATOR . $gf);
            if (!is_file($path)) {
                return false;
            }
        }
        return true;
    };
    if ($cs_gmail_all_in_dir('')) {
        $cs_gmail_step_base = '';
    } else    if ($cs_gmail_all_in_dir('assets/imgs')) {
        $cs_gmail_step_base = 'assets/imgs/';
    }
}

$cs_show_only_below_width = isset($cs_show_only_below_width) ? (int) $cs_show_only_below_width : 0;
if (!isset($cs_show_menu_tools)) {
    $cs_show_menu_tools = true;
}
$cs_is_homepage = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php';
?>
<style>
<?php if ($cs_show_only_below_width > 0): ?>
        @media (min-width: <?php echo $cs_show_only_below_width + 1; ?>px) {
            #csFloat { display: none !important; }
        }
<?php endif; ?>
        :root {
            --cs-widget-size: 240px;
            --cs-widget-gap: 0;
        }

        /* Draggable customer service icon */
        .cs-float-btn {
            position: fixed;
            right: var(--cs-widget-gap);
            bottom: var(--cs-widget-gap);
            left: auto;
            top: auto;
            width: var(--cs-widget-size);
            height: var(--cs-widget-size);
            z-index: 10050;
            cursor: grab;
            user-select: none;
            -webkit-user-select: none;
            touch-action: none;
            background: none;
            border: none;
            box-shadow: none;
            border-radius: 0;
            overflow: visible;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
        }

        .cs-float-btn:active {
            cursor: grabbing;
        }

        .cs-float-btn img {
            display: block;
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            object-position: bottom right;
            pointer-events: none;
            image-rendering: auto;
            -ms-interpolation-mode: bicubic;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
        }

        /* Customer support modal */
        .cs-modal-root {
            position: fixed;
            inset: 0;
            z-index: 10060;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            pointer-events: none;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        .cs-modal-root.is-open {
            pointer-events: auto;
            visibility: visible;
            opacity: 1;
        }

        .cs-modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 18, 28, 0.65);
            backdrop-filter: blur(4px);
        }

        .cs-modal-shell {
            position: relative;
            z-index: 1;
            width: min(500px, calc(100vw - 2.5rem));
            height: min(90vh, 700px);
            max-height: min(90vh, 700px);
            background: linear-gradient(165deg, #2a3148 0%, #1a1f2e 55%, #151923 100%);
            border-radius: 18px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(0.96) translateY(12px);
            transition: transform 0.28s cubic-bezier(0.34, 1.2, 0.64, 1);
        }

        .cs-modal-root.is-open .cs-modal-shell {
            transform: scale(1) translateY(0);
        }

        .cs-modal-header {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 1.25rem 3rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .cs-modal-header h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.02em;
        }

        .cs-modal-close {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }

        .cs-modal-close:hover {
            background: rgba(255, 255, 255, 0.16);
        }

        .cs-modal-body {
            flex: 1 1 auto;
            min-height: 0;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 1.25rem 1.25rem 0.5rem;
        }

        #csModalBodyStack {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        #csModalBodyStack::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        .cs-tab-panel {
            display: none;
            animation: csFadeIn 0.22s ease;
        }

        .cs-tab-panel.is-active {
            display: block;
        }

        @keyframes csFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cs-action-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .cs-action-card {
            background: linear-gradient(145deg, #818cf8 0%, #6366f1 45%, #4f46e5 100%);
            border-radius: 14px;
            padding: 1.25rem 1rem;
            text-decoration: none;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.6rem;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.45);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .cs-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(99, 102, 241, 0.55);
            color: #fff;
        }

        .cs-action-card.cs-action-card--static {
            cursor: default;
            pointer-events: none;
            user-select: text;
            -webkit-user-select: text;
        }

        .cs-action-card.cs-action-card--static:hover {
            transform: none;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.45);
        }

        .cs-action-card i {
            font-size: 1.75rem;
            opacity: 0.95;
            color: #fff;
        }

        .cs-action-card strong {
            font-size: 0.95rem;
            display: block;
        }

        .cs-action-card span {
            font-size: 0.75rem;
            opacity: 0.92;
            line-height: 1.35;
        }

        .cs-section-label {
            color: rgba(255, 255, 255, 0.88);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .cs-faq-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .cs-faq-list li {
            margin-bottom: 0.5rem;
        }

        .cs-faq-list a {
            color: #a5b4fc;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: color 0.2s ease;
        }

        .cs-faq-list a:hover {
            color: #c7d2fe;
            text-decoration: underline;
        }

        .cs-faq-list a i {
            color: inherit;
        }

        .cs-faq-list button.cs-faq-link-btn {
            color: #a5b4fc;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: color 0.2s ease;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
            width: 100%;
        }

        .cs-faq-list button.cs-faq-link-btn:hover {
            color: #c7d2fe;
            text-decoration: underline;
        }

        .cs-faq-list button.cs-faq-link-btn i {
            color: inherit;
        }

        .cs-menu-tools {
            --cs-tool-icon-size: 44px;
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            align-items: center;
            gap: 1.1rem;
            margin: 0.85rem 0 0;
            padding: 0;
            border-top: none;
        }

        .cs-menu-tools .calendar-icon,
        .cs-menu-tools .language-selector {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: var(--cs-tool-icon-size);
            height: var(--cs-tool-icon-size);
            padding: 0;
            border: none;
            border-radius: 0;
            background: none;
            box-shadow: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            cursor: pointer;
            overflow: hidden;
            text-decoration: none;
        }

        .cs-menu-tools .calendar-icon:hover,
        .cs-menu-tools .language-selector:hover {
            transform: scale(1.06);
            opacity: 0.92;
        }

        .cs-menu-tools .calendar-icon-img {
            width: 100%;
            height: 100%;
            max-width: var(--cs-tool-icon-size);
            max-height: var(--cs-tool-icon-size);
            object-fit: contain;
            display: block;
        }

        .cs-menu-tools .language-selector select {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }

        .cs-menu-tools .ph-flag-icon,
        .cs-menu-tools .uk-flag-icon {
            position: absolute;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
            max-width: none;
            max-height: none;
            object-fit: cover;
            border-radius: 6px;
            pointer-events: none;
            transform: none;
            display: block;
        }

        .cs-menu-tools .language-selector i {
            display: none;
        }

        .cs-menu-tools .language-selector.ph-selected .ph-flag-icon {
            opacity: 1;
            display: block !important;
        }

        .cs-menu-tools .language-selector.ph-selected .uk-flag-icon {
            opacity: 0;
            display: none !important;
        }

        .cs-menu-tools .language-selector.en-selected .uk-flag-icon {
            opacity: 1;
            display: block !important;
        }

        .cs-menu-tools .language-selector.en-selected .ph-flag-icon {
            opacity: 0;
            display: none !important;
        }

        .cs-guide-header-bar .cs-modal-close {
            position: static;
            transform: none;
            flex-shrink: 0;
            margin: 0;
        }

        .cs-panel-text {
            color: rgba(255, 255, 255, 0.82);
            font-size: 1.05rem;
            line-height: 1.65;
            margin-bottom: 1.15rem;
        }

        .cs-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .cs-concern-form {
            margin-top: 0.35rem;
        }

        .cs-concern-textarea {
            width: 100%;
            min-height: 120px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.85rem;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.35);
            color: #f1f5f9;
            font-family: inherit;
            font-size: 0.95rem;
            line-height: 1.5;
            resize: vertical;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .cs-concern-textarea::placeholder {
            color: rgba(255, 255, 255, 0.38);
        }

        .cs-concern-textarea:focus {
            outline: none;
            border-color: rgba(165, 180, 252, 0.65);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }

        .cs-concern-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            width: 100%;
            padding: 1rem 1.25rem;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 50%, #4f46e5 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1.02rem;
            font-family: inherit;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.45);
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }

        .cs-concern-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(99, 102, 241, 0.55);
        }

        .cs-concern-submit:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .cs-concern-submit i {
            color: #fff;
        }

        .cs-concern-feedback {
            margin: 0.85rem 0 0;
            font-size: 0.9rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.88);
        }

        .cs-concern-feedback.is-error {
            color: #fca5a5;
        }

        .cs-concern-feedback.is-success {
            color: #86efac;
        }

        .cs-modal-footer {
            flex-shrink: 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(0, 0, 0, 0.25);
            padding: 0.35rem 0 0.5rem;
        }

        .cs-modal-tab {
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.45);
            cursor: pointer;
            padding: 0.5rem 0.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            font-family: inherit;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
            transition: color 0.2s ease;
        }

        .cs-modal-tab i {
            font-size: 1.35rem;
            margin-bottom: 0.1rem;
        }

        .cs-modal-tab:hover {
            color: rgba(255, 255, 255, 0.75);
        }

        .cs-modal-tab.is-active {
            color: #a5b4fc;
        }

        .cs-modal-tab.is-active i {
            color: #c7d2fe;
        }

        .cs-header-default-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            width: 100%;
            min-height: 2.25rem;
        }

        .cs-modal-header.is-guide-mode .cs-header-default-inner {
            visibility: hidden;
            pointer-events: none;
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
        }

        .cs-guide-header-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            width: 100%;
            padding: 0 0.35rem 0 0.15rem;
        }

        .cs-modal-header.is-guide-mode .cs-guide-header-bar {
            display: flex;
        }

        .cs-guide-back {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.2s ease;
        }

        .cs-guide-back:hover {
            background: rgba(255, 255, 255, 0.16);
        }

        .cs-guide-bar-title {
            flex: 1;
            text-align: center;
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.02em;
        }

        .cs-modal-footer.is-hidden-for-guide {
            display: none;
        }

        .cs-register-guide {
            display: none;
            flex-direction: column;
            position: absolute;
            inset: 0;
            z-index: 4;
            background: linear-gradient(165deg, #2a3148 0%, #1a1f2e 55%, #151923 100%);
            overflow: hidden;
        }

        .cs-register-guide.is-open {
            display: flex;
        }

        #csEmailHelpGuide.cs-register-guide {
            z-index: 5;
        }

        .cs-guide-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 1.1rem 1.25rem 1rem;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            margin: 0 0.35rem 0.5rem;
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }

        .cs-guide-page-title {
            margin: 0 0 1.25rem;
            font-size: 1.08rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.35;
        }

        .cs-guide-step {
            margin-bottom: 1.35rem;
        }

        .cs-guide-step:last-of-type {
            margin-bottom: 0.5rem;
        }

        .cs-guide-step-title {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #c7d2fe;
            margin: 0 0 0.45rem;
        }

        .cs-guide-step-text {
            margin: 0;
            font-size: 0.94rem;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.88);
        }

        .cs-gmail-visual-guide {
            margin-top: 1.75rem;
            padding-top: 1.35rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cs-gmail-visual-guide-title {
            margin: 0 0 1.35rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            text-align: center;
            line-height: 1.35;
            letter-spacing: 0.01em;
        }

        .cs-gmail-steps-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem 0.85rem;
            align-items: start;
        }

        .cs-gmail-step-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
        }

        .cs-gmail-step-cell img {
            width: 100%;
            max-width: min(220px, 42vw);
            height: auto;
            display: block;
            border-radius: 12px;
            object-fit: contain;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
        }

        .cs-gmail-step-caption {
            margin: 0;
            font-size: 0.78rem;
            font-weight: 400;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.92);
            text-align: center;
            padding: 0 0.15rem;
        }

        .cs-guide-footer-cta {
            padding: 0.5rem 1.25rem 0.85rem;
            flex-shrink: 0;
        }

        .cs-guide-open-form {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.9rem 1rem;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 50%, #4f46e5 100%);
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .cs-guide-open-form:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(99, 102, 241, 0.5);
            color: #fff;
        }

        .cs-guide-external-link {
            color: #fcb07e;
            text-decoration: underline;
            text-underline-offset: 2px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
        }

        .cs-guide-external-link:hover {
            color: #ffd4b8;
        }

        .cs-guide-external-link i {
            color: #fcb07e;
            font-size: 0.75em;
            opacity: 0.95;
        }

        .cs-guide-external-link:hover i {
            color: #ffd4b8;
        }

        .cs-guide-external-link:focus-visible {
            outline: 2px solid #fcb07e;
            outline-offset: 3px;
            border-radius: 4px;
        }

        .cs-guide-sublist {
            margin: 0.5rem 0 0;
            padding: 0;
            list-style: none;
        }

        .cs-guide-sublist li {
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.35rem;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.94rem;
            line-height: 1.45;
            background: rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.22);
        }

        .cs-guide-sublist li:last-child {
            margin-bottom: 0;
        }

        .cs-guide-google-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.9rem 1rem;
            border: none;
            border-radius: 12px;
            background: #4f46e5;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 8px 22px rgba(79, 70, 229, 0.45);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .cs-guide-google-cta:hover {
            transform: translateY(-1px);
            background: #4338ca;
            box-shadow: 0 10px 26px rgba(79, 70, 229, 0.55);
            color: #fff;
        }

        .cs-guide-google-cta:focus-visible {
            outline: 2px solid #c7d2fe;
            outline-offset: 3px;
        }

        @media (max-width: 991px) {
            .cs-float-btn {
                right: 0;
                bottom: calc(5.75rem + env(safe-area-inset-bottom, 0px));
            }

            .cs-modal-root {
                padding: 0.55rem;
                align-items: center;
            }

            .cs-modal-shell {
                width: min(100%, calc(100vw - 1.1rem));
                height: auto;
                max-height: calc(100dvh - 1rem);
            }

            .cs-modal-header {
                padding: 0.65rem 2.5rem 0.5rem;
            }

            .cs-modal-header h2 {
                font-size: 0.95rem;
            }

            .cs-modal-body {
                padding: 0.65rem 0.8rem 0.25rem;
            }

            .cs-action-cards {
                grid-template-columns: 1fr 1fr;
                gap: 0.45rem;
                margin-bottom: 0.55rem;
            }

            .cs-action-card {
                padding: 0.55rem 0.4rem;
                gap: 0.22rem;
                border-radius: 12px;
            }

            .cs-action-card i {
                font-size: 1.05rem;
            }

            .cs-action-card strong {
                font-size: 0.75rem;
            }

            .cs-action-card span {
                font-size: 0.62rem;
                line-height: 1.25;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .cs-section-label {
                font-size: 0.75rem;
                margin-bottom: 0.28rem;
            }

            .cs-faq-list li {
                margin-bottom: 0.18rem;
            }

            .cs-faq-list a,
            .cs-faq-list button.cs-faq-link-btn {
                font-size: 0.74rem;
                line-height: 1.25;
                gap: 0.25rem;
            }

            .cs-menu-tools {
                --cs-tool-icon-size: 36px;
                gap: 0.75rem;
                margin: 0.4rem 0 0.15rem;
            }

            .cs-modal-footer {
                padding: 0.2rem 0 0.3rem;
            }

            .cs-modal-tab {
                font-size: 0.62rem;
                padding: 0.35rem 0.15rem;
                gap: 0.12rem;
            }

            .cs-modal-tab i {
                font-size: 1.05rem;
                margin-bottom: 0;
            }
        }

        .dpm-cal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10080;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(46, 24, 78, 0.28);
        }

        .dpm-cal-overlay.is-open {
            display: flex;
        }

        .dpm-cal-card {
            position: relative;
            width: min(340px, 100%);
            background: #ffffff;
            border-radius: 28px;
            padding: 1.35rem 1.25rem 1.15rem;
            box-shadow: 0 24px 60px rgba(92, 58, 140, 0.22);
            font-family: Inter, system-ui, sans-serif;
            color: #1c1c1e;
        }

        .dpm-cal-close {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 50%;
            background: transparent;
            color: #9b8aaf;
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
            margin-top: -0.15rem;
        }

        .dpm-cal-top {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
        }

        .dpm-cal-day {
            font-size: 3.4rem;
            line-height: 0.9;
            font-weight: 700;
            color: #7b4dcc;
            letter-spacing: -0.04em;
        }

        .dpm-cal-monthline {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin: -0.35rem 0 0.9rem;
        }

        .dpm-cal-month {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1c1c1e;
            white-space: nowrap;
        }

        .dpm-cal-shift {
            width: 22px;
            height: 22px;
            border: none;
            border-radius: 50%;
            background: #f3e8ff;
            color: #7b4dcc;
            font-size: 1rem;
            line-height: 1;
            cursor: pointer;
            padding: 0;
        }

        .dpm-cal-art {
            width: 74px;
            height: 74px;
            flex-shrink: 0;
            margin-left: auto;
        }

        .dpm-cal-week {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.15rem;
            background: #f6f0fc;
            border-radius: 999px;
            padding: 0.4rem 0.35rem;
            margin-bottom: 0.7rem;
        }

        .dpm-cal-week span {
            text-align: center;
            font-size: 0.72rem;
            font-weight: 600;
            color: #b08ad4;
        }

        .dpm-cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.35rem 0.15rem;
        }

        .dpm-cal-cell {
            border: none;
            background: transparent;
            height: 32px;
            border-radius: 50%;
            font-size: 0.92rem;
            font-weight: 600;
            color: #1c1c1e;
            cursor: pointer;
            padding: 0;
        }

        .dpm-cal-cell.is-muted {
            color: #c5c5cc;
            font-weight: 500;
        }

        .dpm-cal-cell.is-selected {
            background: #7b4dcc;
            color: #ffffff;
        }

        @media (max-width: 520px) {
            :root {
                --cs-widget-size: 200px;
                --cs-widget-gap: 0;
            }

            .cs-float-btn {
                bottom: calc(5.25rem + env(safe-area-inset-bottom, 0px));
            }
        }
</style>

    <div id="csFloat" class="cs-float-btn" role="button" tabindex="0" aria-label="Open customer support" aria-haspopup="dialog" aria-controls="csSupportModal">
        <img src="<?php echo $cs_img_esc; ?>" alt="" draggable="false" decoding="sync">
    </div>

    <div class="cs-modal-root" id="csModalPortal" aria-hidden="true">
        <div class="cs-modal-overlay" id="csModalOverlay" tabindex="-1"></div>
        <div id="csSupportModal" class="cs-modal-shell" role="dialog" aria-modal="true" aria-labelledby="csModalTitle">
            <div class="cs-modal-header" id="csModalHeaderBar">
                <div class="cs-header-default-inner">
                    <h2 id="csModalTitle">Customer Support</h2>
                    <button type="button" class="cs-modal-close" id="csModalClose" aria-label="Close support">&times;</button>
                </div>
                <div class="cs-guide-header-bar" aria-hidden="true">
                    <button type="button" class="cs-guide-back" id="csGuideBack" aria-label="Back to support"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                    <span class="cs-guide-bar-title" id="csGuideBarTitle">Support</span>
                    <button type="button" class="cs-modal-close" id="csGuideCloseBtn" aria-label="Close support">&times;</button>
                </div>
            </div>
            <div class="cs-modal-body">
                <div id="csModalBodyStack">
                <div class="cs-tab-panel is-active" data-cs-panel="support" id="csPanelSupport">
                    <div class="cs-action-cards">
                        <a class="cs-action-card" href="https://www.facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer" data-cs-close-modal="1">
                            <i class="fab fa-facebook-f" aria-hidden="true"></i>
                            <strong data-cs-i18n="fb">Facebook</strong>
                            <span data-cs-i18n="fbSub">Official page &amp; updates</span>
                        </a>
                        <div class="cs-action-card cs-action-card--static" aria-label="Market location">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <strong data-cs-i18n="loc">Location</strong>
                            <span data-cs-i18n="locSub">J. Lukban Street, Bagasbas Road, Brgy. VII, Daet, Philippines, 4600</span>
                        </div>
                    </div>
                    <p class="cs-section-label" data-cs-i18n="needHelp">Need help?</p>
                    <ul class="cs-faq-list">
                        <li><button type="button" class="cs-faq-link-btn cs-open-register-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qRegister">How to register in market clearance</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-apply-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qApply">How to apply for a market slot</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-login-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qLogin">How to log in to your account</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-receipt-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qReceipt">How to send receipt</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-renewal-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qRenew">How to renew your clearance</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-email-help-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qGmail">Don&apos;t have a Gmail account?</span></button></li>
                    </ul>
                    <?php if (!empty($cs_show_menu_tools)): ?>
                    <div class="cs-menu-tools" aria-label="Calendar and language">
                        <a href="#" class="calendar-icon" title="Calendar" id="calendarIconLinkDesktop" data-cs-close-modal="1">
                            <img src="assets/imgs/calendar-icon.png" alt="Calendar" class="calendar-icon-img">
                        </a>
                        <div class="language-selector en-selected" id="languageSelectorDesktop">
                            <i class="fas fa-globe" aria-hidden="true"></i>
                            <img src="assets/imgs/uk-flag.jpg" alt="UK Flag" class="uk-flag-icon">
                            <img src="assets/imgs/ph-flag.png" alt="Philippines Flag" class="ph-flag-icon">
                            <select id="languageSelectDesktop" aria-label="Language">
                                <option value="en">EN</option>
                                <option value="ph">PH</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="cs-tab-panel" data-cs-panel="live" id="csPanelLive">
                    <p class="cs-section-label" data-cs-i18n="concerns">Concerns</p>
                    <p class="cs-panel-text" data-cs-i18n="concernsText">
                        We hear your voice. Have complaints or feedback? Let us know so we can improve our services.
                    </p>
                    <form class="cs-concern-form" id="csConcernForm" action="#" method="post" novalidate>
                        <label for="csConcernMessage" class="cs-sr-only">Your concern or feedback</label>
                        <textarea id="csConcernMessage" name="message" class="cs-concern-textarea" rows="4" maxlength="5000" required placeholder="Type your concern or feedback here…" data-cs-i18n-placeholder="concernPh" autocomplete="off"></textarea>
                        <button type="submit" class="cs-concern-submit" id="csConcernSubmit">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i>
                            <span data-cs-i18n="send">Send</span>
                        </button>
                        <p class="cs-concern-feedback" id="csConcernFeedback" role="status" aria-live="polite"></p>
                    </form>
                </div>
                <div class="cs-tab-panel" data-cs-panel="faq" id="csPanelFaq">
                    <p class="cs-section-label" data-cs-i18n="ask">Do you want to ask the following?</p>
                    <ul class="cs-faq-list">
                        <li><button type="button" class="cs-faq-link-btn cs-open-register-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qRegister">How to register in market clearance</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-apply-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qApply">How to apply for a market slot</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-login-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qLogin">How to log in to your account</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-receipt-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qReceipt">How to send receipt</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-renewal-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qRenew">How to renew your clearance</span></button></li>
                        <li><button type="button" class="cs-faq-link-btn cs-open-email-help-guide"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qGmail">Don&apos;t have a Gmail account?</span></button></li>
                        <?php if ($cs_announcement_href !== ''): ?>
                        <li><a href="<?php echo htmlspecialchars($cs_announcement_href, ENT_QUOTES, 'UTF-8'); ?>" data-cs-close-modal="1"><i class="fas fa-chevron-right" aria-hidden="true"></i> <span data-cs-i18n="qAnnounce">View homepage announcement</span></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                </div>
                <div id="csRegisterGuide" class="cs-register-guide" aria-hidden="true" role="region" aria-labelledby="csRegisterGuideTitle">
                    <div class="cs-guide-scroll">
                        <h3 id="csRegisterGuideTitle" class="cs-guide-page-title" data-cs-i18n="gRegTitle">How to Register in Market Clearance?</h3>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 1: Open the registration page</p>
                            <p class="cs-guide-step-text">From the Daet Public Market site, go to the <strong>Registration</strong> page (or tap <strong>Go to registration form</strong> below when you are ready). If you already have an account, use <strong>Log in</strong> instead.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 2: Enter your personal details</p>
                            <p class="cs-guide-step-text">Fill in your <strong>full name</strong>, a valid <strong>email address</strong>, and <strong>mobile number</strong>. These are used for your account and important updates from the market office.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 3: Create a secure password</p>
                            <p class="cs-guide-step-text">Choose a password with <strong>at least 8 characters</strong>, including <strong>one uppercase letter</strong>, <strong>one lowercase letter</strong>, and <strong>one number</strong>. Type it again in the confirm field to match.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 4: Accept the terms and submit</p>
                            <p class="cs-guide-step-text">Read the <strong>Terms &amp; Conditions</strong>, check the box to accept them, then submit the form.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 5: Sign in and continue</p>
                            <p class="cs-guide-step-text">If registration succeeds, sign in with your email and password. You can then apply for a market slot, upload documents, and manage your clearance from your dashboard.</p>
                        </div>
                    </div>
                    <div class="cs-guide-footer-cta">
                        <a href="registrationform.php" class="cs-guide-open-form" data-cs-close-modal="1"><i class="fas fa-user-plus" aria-hidden="true"></i> Go to registration form</a>
                    </div>
                </div>
                <div id="csEmailHelpGuide" class="cs-register-guide" aria-hidden="true" role="region" aria-labelledby="csEmailHelpGuideTitle">
                    <div class="cs-guide-scroll">
                        <h3 id="csEmailHelpGuideTitle" class="cs-guide-page-title" data-cs-i18n="gMailTitle">Don&apos;t have a Gmail account?</h3>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 1: Open Google sign-in</p>
                            <p class="cs-guide-step-text">From your device, go to the <a class="cs-guide-external-link" href="<?php echo $cs_google_account_url_esc; ?>" target="_blank" rel="noopener noreferrer">Google Account sign in page<i class="fas fa-external-link-alt" aria-hidden="true"></i></a>.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 2: Start creating an account</p>
                            <p class="cs-guide-step-text">Click <strong>Create account</strong>.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 3: Choose who the account is for</p>
                            <p class="cs-guide-step-text">In the drop-down, select if the account is for your:</p>
                            <ul class="cs-guide-sublist">
                                <li>Personal use</li>
                                <li>Child</li>
                                <li>Work or business</li>
                            </ul>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 4: Finish on Google</p>
                            <p class="cs-guide-step-text">To set up your account, follow the steps below.</p>
                        </div>
                        <div class="cs-gmail-visual-guide" aria-labelledby="csGmailVisualGuideTitle">
                            <h4 id="csGmailVisualGuideTitle" class="cs-gmail-visual-guide-title">How to create Gmail Account</h4>
                            <div class="cs-gmail-steps-grid">
                                <div class="cs-gmail-step-cell">
                                    <img src="<?php echo htmlspecialchars($cs_gmail_step_base . 'firststep.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="Gmail signup: first and last name" width="360" height="640" loading="lazy" decoding="async">
                                    <p class="cs-gmail-step-caption">a. fill up you first name and last name</p>
                                </div>
                                <div class="cs-gmail-step-cell">
                                    <img src="<?php echo htmlspecialchars($cs_gmail_step_base . 'secondstep.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="Gmail signup: date of birth" width="360" height="640" loading="lazy" decoding="async">
                                    <p class="cs-gmail-step-caption">b. enter your date of birth</p>
                                </div>
                                <div class="cs-gmail-step-cell">
                                    <img src="<?php echo htmlspecialchars($cs_gmail_step_base . 'thirdstep.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="Gmail signup: username" width="360" height="640" loading="lazy" decoding="async">
                                    <p class="cs-gmail-step-caption">c. type the username you want</p>
                                </div>
                                <div class="cs-gmail-step-cell">
                                    <img src="<?php echo htmlspecialchars($cs_gmail_step_base . 'fourthstep.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="Gmail signup: password" width="360" height="640" loading="lazy" decoding="async">
                                    <p class="cs-gmail-step-caption">d. enter your desire password</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="cs-guide-footer-cta">
                        <a href="<?php echo $cs_google_account_url_esc; ?>" class="cs-guide-google-cta" target="_blank" rel="noopener noreferrer" data-cs-close-modal="1">Create an account</a>
                    </div>
                </div>
                <div id="csApplyGuide" class="cs-register-guide" aria-hidden="true" role="region" aria-labelledby="csApplyGuideTitle">
                    <div class="cs-guide-scroll">
                        <h3 id="csApplyGuideTitle" class="cs-guide-page-title" data-cs-i18n="gApplyTitle">How to apply for a market slot?</h3>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 1: Sign in</p>
                            <p class="cs-guide-step-text">You must be logged in. If you do not have an account yet, register first, then return here and open <strong>Application</strong> from the menu.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 2: Open the application page</p>
                            <p class="cs-guide-step-text">From the site navigation, go to <strong>Application</strong> (New Application). You will see the clearance application workflow.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 3: Choose how many stalls</p>
                            <p class="cs-guide-step-text">Select the <strong>number of stalls</strong> or slots you need (or enter a custom count if that option is shown). The form will create one section per stall.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 4: Fill in each stall</p>
                            <p class="cs-guide-step-text">For every stall, enter the <strong>full name</strong>, choose the <strong>section</strong> and <strong>block</strong>, and <strong>upload the required documents</strong> (for example ID, DTI, barangay clearance, and any other files listed on the form). All marked fields must be completed.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 5: Submit</p>
                            <p class="cs-guide-step-text">Review your entries, then submit. Wait for notifications or check your application status from your profile or dashboard as instructed by the market office.</p>
                        </div>
                    </div>
                    <div class="cs-guide-footer-cta">
                        <a href="application.php" class="cs-guide-open-form" data-cs-close-modal="1"><i class="fas fa-file-alt" aria-hidden="true"></i> Go to application</a>
                    </div>
                </div>
                <div id="csLoginGuide" class="cs-register-guide" aria-hidden="true" role="region" aria-labelledby="csLoginGuideTitle">
                    <div class="cs-guide-scroll">
                        <h3 id="csLoginGuideTitle" class="cs-guide-page-title" data-cs-i18n="gLoginTitle">How to log in to your account?</h3>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 1: Open the login page</p>
                            <p class="cs-guide-step-text">Go to the <strong>Log in</strong> page on the Daet Public Market site (for example from the home or registration page).</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 2: Enter your credentials</p>
                            <p class="cs-guide-step-text">Type the <strong>email address</strong> and <strong>password</strong> you used when you registered. Check for typos and correct capitalization.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 3: Sign in</p>
                            <p class="cs-guide-step-text">Click the sign-in or log-in button. If you forgot your password, use the <strong>forgot password</strong> or reset link on the login page, if available.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 4: After you are logged in</p>
                            <p class="cs-guide-step-text">Use the navigation to open <strong>Home</strong>, <strong>Application</strong>, <strong>Renewal</strong>, <strong>Profile</strong>, or <strong>Notifications</strong> depending on what you need.</p>
                        </div>
                    </div>
                    <div class="cs-guide-footer-cta">
                        <a href="userlogin.php" class="cs-guide-open-form" data-cs-close-modal="1"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Go to login</a>
                    </div>
                </div>
                <div id="csReceiptGuide" class="cs-register-guide" aria-hidden="true" role="region" aria-labelledby="csReceiptGuideTitle">
                    <div class="cs-guide-scroll">
                        <h3 id="csReceiptGuideTitle" class="cs-guide-page-title" data-cs-i18n="gReceiptTitle">How to Send a Receipt in Market Clearance?</h3>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 1: Sign in to your account</p>
                            <p class="cs-guide-step-text">Uploading a receipt requires an active session. Open the <strong>Log in</strong> page, enter the <strong>email</strong> and <strong>password</strong> you used when you registered, and sign in. If you are already logged in, you can skip to the next step.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 2: Open your Profile</p>
                            <p class="cs-guide-step-text">From the top or side navigation on the Daet Public Market site, tap or click <strong>Profile</strong>. Your profile shows your applications, account tools, and the <strong>Send receipt</strong> section.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 3: Expand &ldquo;Send receipt&rdquo;</p>
                            <p class="cs-guide-step-text">Scroll until you see the <strong>Send receipt</strong> card (it works like <strong>Edit Profile</strong> or <strong>Change Password</strong>: one row you tap to open). Tap the row or the chevron so the panel opens and the upload form appears.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 4: Attach your receipt image</p>
                            <p class="cs-guide-step-text">Tap <strong>Choose image</strong> (or the file area). Your device will ask you to pick a photo or file. Select a <strong>clear, readable</strong> picture of your official payment receipt.</p>
                            <ul class="cs-guide-sublist">
                                <li><strong>Allowed types:</strong> JPEG, JPG, PNG, or GIF</li>
                                <li><strong>Maximum size:</strong> 5 MB</li>
                                <li><strong>Tip:</strong> Avoid blurry or cropped images so the office can verify amount, date, and reference details.</li>
                            </ul>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 5: Add notes (optional)</p>
                            <p class="cs-guide-step-text">In <strong>Notes (optional)</strong>, you may type a short message for the market office if you wish. This field can be left blank.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 6: Submit the receipt</p>
                            <p class="cs-guide-step-text">Review that the correct file name appears next to <strong>Choose image</strong>, then tap <strong>Submit receipt</strong>. Wait until the page shows a <strong>success</strong> message; do not close the browser while it is uploading.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 7: After you submit</p>
                            <p class="cs-guide-step-text">The market office will review your upload. You can track activity from your profile:</p>
                            <ul class="cs-guide-sublist">
                                <li><strong>Activity log</strong> on Profile lists your receipt submission</li>
                                <li><strong>Notifications</strong> may alert you when the status changes</li>
                                <li><strong>Email:</strong> If your account has a valid address, you may get a confirmation that shows <strong>when you sent</strong> the receipt and your <strong>due date</strong>. The review period is one month from when you sent the receipt. You get an email each day from 7 days before the due date down to the due date. Check your spam folder if it does not arrive.</li>
                                <li>If something fail, it may because your receipt is invalid. Go to send receipt section again and send another receipt that is valid, or contact us using <strong>Facebook</strong>.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="cs-guide-footer-cta">
                        <a href="profile.php" class="cs-guide-open-form" data-cs-close-modal="1"><i class="fas fa-receipt" aria-hidden="true"></i> Go to profile</a>
                    </div>
                </div>
                <div id="csRenewalGuide" class="cs-register-guide" aria-hidden="true" role="region" aria-labelledby="csRenewalGuideTitle">
                    <div class="cs-guide-scroll">
                        <h3 id="csRenewalGuideTitle" class="cs-guide-page-title" data-cs-i18n="gRenewTitle">How to renew your clearance?</h3>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 1: Sign in</p>
                            <p class="cs-guide-step-text">Renewal is for existing vendors. Log in with the account tied to your stall or clearance.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 2: Open Renewal</p>
                            <p class="cs-guide-step-text">From the menu, go to <strong>Renewal</strong>. The page loads your renewal form and any section or block information carried from your previous application when the system provides it.</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 3: Update details and documents</p>
                            <p class="cs-guide-step-text">Review and update your information as needed. Upload or replace <strong>renewal requirements</strong> shown on the page (for example clearances, receipts, or other documents listed by the market office).</p>
                        </div>
                        <div class="cs-guide-step">
                            <p class="cs-guide-step-title">Step 4: Submit and follow up</p>
                            <p class="cs-guide-step-text">Submit the renewal form. Watch for approval messages or notifications, and contact the market office if you need help with status or missing requirements.</p>
                        </div>
                    </div>
                    <div class="cs-guide-footer-cta">
                        <a href="renewal.php" class="cs-guide-open-form" data-cs-close-modal="1"><i class="fas fa-sync-alt" aria-hidden="true"></i> Go to renewal</a>
                    </div>
                </div>
            </div>
            <div class="cs-modal-footer" role="tablist" aria-label="Support sections">
                <button type="button" class="cs-modal-tab is-active" role="tab" aria-selected="true" aria-controls="csPanelSupport" id="csTabSupport" data-cs-tab="support">
                    <i class="fas fa-headset" aria-hidden="true"></i>
                    <span data-cs-i18n="tabSupport">Support</span>
                </button>
                <button type="button" class="cs-modal-tab" role="tab" aria-selected="false" aria-controls="csPanelLive" id="csTabLive" data-cs-tab="live">
                    <i class="fas fa-comment-dots" aria-hidden="true"></i>
                    <span data-cs-i18n="tabConcerns">Concerns</span>
                </button>
                <button type="button" class="cs-modal-tab" role="tab" aria-selected="false" aria-controls="csPanelFaq" id="csTabFaq" data-cs-tab="faq">
                    <i class="fas fa-bullhorn" aria-hidden="true"></i>
                    <span data-cs-i18n="tabFaq">FAQ &amp; Announcement</span>
                </button>
            </div>
        </div>
    </div>

    <div class="dpm-cal-overlay" id="csCalendarModal" aria-hidden="true">
        <div class="dpm-cal-card" role="dialog" aria-modal="true" aria-labelledby="dpmCalHeading">
            <div class="dpm-cal-top">
                <div class="dpm-cal-day" id="dpmCalDay">1</div>
                <svg class="dpm-cal-art" viewBox="0 0 74 74" aria-hidden="true">
                    <rect x="8" y="16" width="58" height="50" rx="12" fill="#f4f0fb" stroke="#e4d7f5" stroke-width="2"/>
                    <path d="M8 28h58v-4a12 12 0 0 0-12-12H20a12 12 0 0 0-12 12v4z" fill="#ef4444"/>
                    <rect x="22" y="8" width="6" height="14" rx="3" fill="#d6d0de"/>
                    <rect x="46" y="8" width="6" height="14" rx="3" fill="#d6d0de"/>
                    <circle cx="37" cy="46" r="12" fill="#ffffff" stroke="#e4d7f5" stroke-width="2"/>
                    <path d="M37 40v7l4 3" fill="none" stroke="#b08ad4" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <button type="button" class="dpm-cal-close" id="dpmCalClose" aria-label="Close">&times;</button>
            </div>
            <div class="dpm-cal-monthline">
                <button type="button" class="dpm-cal-shift" id="dpmCalPrev" aria-label="Previous month">&lsaquo;</button>
                <div class="dpm-cal-month" id="dpmCalHeading">January, 2026</div>
                <button type="button" class="dpm-cal-shift" id="dpmCalNext" aria-label="Next month">&rsaquo;</button>
            </div>
            <div class="dpm-cal-week" aria-hidden="true">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>
            <div class="dpm-cal-grid" id="dpmCalGrid"></div>
        </div>
    </div>

    <?php
    $__dpm_web = isset($dpm_web_root) ? rtrim((string) $dpm_web_root, '/') . '/' : '';
    $__dpm_page = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    ?>
    <script>window.DPM_I18N_PAGE=<?php echo json_encode($__dpm_page, JSON_UNESCAPED_UNICODE); ?>;</script>
    <script>
    window.DPM_SITE_I18N = window.DPM_SITE_I18N || {};
    window.DPM_SITE_I18N.ph = Object.assign(window.DPM_SITE_I18N.ph || {}, { 'idx.notice': 'Paunawa' });
    </script>
    <script src="<?php echo htmlspecialchars($__dpm_web . 'assets/js/dpm-site-i18n.js', ENT_QUOTES, 'UTF-8'); ?>?v=2"></script>
    <?php if (in_array($__dpm_page, ['registrationform', 'promotional-permit'], true)): ?>
    <script src="<?php echo htmlspecialchars($__dpm_web . 'assets/js/dpm-i18n-reg-promo.js', ENT_QUOTES, 'UTF-8'); ?>?v=4"></script>
    <?php endif; ?>

    <script>
    (function () {
        var CS_PH = {
            fb: 'Facebook',
            fbSub: 'Opisyal na page at mga update',
            loc: 'Lokasyon',
            locSub: 'J. Lukban Street, Bagasbas Road, Brgy. VII, Daet, Philippines, 4600',
            needHelp: 'Kailangan ng tulong?',
            concerns: 'Mga Alalahanin',
            concernsText: 'Naririnig namin kayo. May reklamo o suhestiyon? Ipaalam sa amin para mapaganda ang serbisyo.',
            concernPh: 'I-type dito ang iyong alalahanin o suhestiyon…',
            send: 'Ipadala',
            ask: 'Gusto mo bang itanong ang mga sumusunod?',
            tabSupport: 'Suporta',
            tabConcerns: 'Mga Alalahanin',
            tabFaq: 'FAQ at Anunsyo',
            qRegister: 'Paano magrehistro sa market clearance',
            qApply: 'Paano mag-apply ng market slot',
            qLogin: 'Paano mag-log in sa account',
            qReceipt: 'Paano magpadala ng resibo',
            qRenew: 'Paano i-renew ang clearance',
            qGmail: 'Wala kang Gmail account?',
            qAnnounce: 'Tingnan ang anunsyo sa homepage',
            gRegTitle: 'Paano Magrehistro sa Market Clearance?',
            gMailTitle: 'Wala kang Gmail account?',
            gApplyTitle: 'Paano mag-apply ng market slot?',
            gLoginTitle: 'Paano mag-log in sa iyong account?',
            gReceiptTitle: 'Paano Magpadala ng Resibo sa Market Clearance?',
            gRenewTitle: 'Paano i-renew ang iyong clearance?',
            calTitle: 'Kalendaryo',
            calSoon: 'Malapit na ang kalendaryo'
        };

        function applyCsLanguage(lang) {
            var ph = lang === 'ph';
            document.querySelectorAll('[data-cs-i18n]').forEach(function (node) {
                var key = node.getAttribute('data-cs-i18n');
                if (!node.getAttribute('data-cs-en')) node.setAttribute('data-cs-en', node.textContent);
                node.textContent = (ph && CS_PH[key]) ? CS_PH[key] : node.getAttribute('data-cs-en');
            });
            document.querySelectorAll('[data-cs-i18n-placeholder]').forEach(function (node) {
                var key = node.getAttribute('data-cs-i18n-placeholder');
                if (!node.getAttribute('data-cs-en-ph')) node.setAttribute('data-cs-en-ph', node.getAttribute('placeholder') || '');
                node.setAttribute('placeholder', (ph && CS_PH[key]) ? CS_PH[key] : node.getAttribute('data-cs-en-ph'));
            });
            var sel = document.getElementById('languageSelectDesktop');
            var wrap = document.getElementById('languageSelectorDesktop');
            if (sel) sel.value = ph ? 'ph' : 'en';
            if (wrap) {
                wrap.classList.remove('ph-selected', 'en-selected');
                wrap.classList.add(ph ? 'ph-selected' : 'en-selected');
            }
            try { localStorage.setItem('preferredLanguage', ph ? 'ph' : 'en'); } catch (e) {}
        }
        window.applyCsLanguage = applyCsLanguage;

        var langNow = 'en';
        try { langNow = localStorage.getItem('preferredLanguage') || 'en'; } catch (err) {}
        if (typeof window.dpmSyncSiteLanguage === 'function') {
            window.dpmSyncSiteLanguage(langNow);
        } else {
            applyCsLanguage(langNow);
        }
        (function () {
            var overlay = document.getElementById('csCalendarModal');
            var grid = document.getElementById('dpmCalGrid');
            var dayEl = document.getElementById('dpmCalDay');
            var heading = document.getElementById('dpmCalHeading');
            if (!overlay || !grid || !dayEl || !heading) return;
            var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            var view = new Date();
            var selected = new Date();
            function sameDay(a, b) {
                return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
            }
            function renderCal() {
                var year = view.getFullYear();
                var month = view.getMonth();
                dayEl.textContent = String(selected.getDate());
                heading.textContent = months[view.getMonth()] + ', ' + view.getFullYear();
                var first = new Date(year, month, 1);
                var start = new Date(year, month, 1 - first.getDay());
                grid.innerHTML = '';
                for (var i = 0; i < 42; i++) {
                    var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'dpm-cal-cell';
                    btn.textContent = String(d.getDate());
                    if (d.getMonth() !== month) btn.classList.add('is-muted');
                    if (sameDay(d, selected)) btn.classList.add('is-selected');
                    btn.addEventListener('click', function (date) {
                        return function () {
                            selected = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                            view = new Date(selected.getFullYear(), selected.getMonth(), 1);
                            renderCal();
                        };
                    }(d));
                    grid.appendChild(btn);
                }
            }
            function openCal() {
                view = new Date(selected.getFullYear(), selected.getMonth(), 1);
                renderCal();
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
            }
            function closeCal() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
            }
            function shiftMonth(delta) {
                view = new Date(view.getFullYear(), view.getMonth() + delta, 1);
                selected = new Date(view.getFullYear(), view.getMonth(), 1);
                renderCal();
            }
            document.getElementById('dpmCalPrev').addEventListener('click', function () { shiftMonth(-1); });
            document.getElementById('dpmCalNext').addEventListener('click', function () { shiftMonth(1); });
            document.getElementById('dpmCalClose').addEventListener('click', closeCal);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeCal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeCal();
            });
            document.addEventListener('click', function (e) {
                var link = e.target.closest && e.target.closest('.calendar-icon');
                if (!link) return;
                e.preventDefault();
                openCal();
            }, true);
            renderCal();
        })();
        var langSel = document.getElementById('languageSelectDesktop');
        if (langSel) {
            langSel.addEventListener('change', function () {
                if (typeof window.dpmSyncSiteLanguage === 'function') {
                    window.dpmSyncSiteLanguage(this.value);
                } else {
                    applyCsLanguage(this.value);
                    if (typeof window.changeLanguage === 'function') window.changeLanguage(this.value);
                    else if (typeof window.translatePage === 'function') window.translatePage(this.value);
                }
            });
        }
        window.addEventListener('menu-lang-change', function (ev) {
            var next = (ev.detail && ev.detail.lang) || 'en';
            applyCsLanguage(next);
        });

        var el = document.getElementById('csFloat');
        var modal = document.getElementById('csModalPortal');
        var overlay = document.getElementById('csModalOverlay');
        var closeBtn = document.getElementById('csModalClose');
        var guideCloseBtn = document.getElementById('csGuideCloseBtn');
        var guideBackBtn = document.getElementById('csGuideBack');
        var headerBar = document.getElementById('csModalHeaderBar');
        var guideBar = modal.querySelector('.cs-guide-header-bar');
        var registerGuide = document.getElementById('csRegisterGuide');
        var emailHelpGuide = document.getElementById('csEmailHelpGuide');
        var applyGuide = document.getElementById('csApplyGuide');
        var loginGuide = document.getElementById('csLoginGuide');
        var receiptGuide = document.getElementById('csReceiptGuide');
        var renewalGuide = document.getElementById('csRenewalGuide');
        var guideTitle = document.getElementById('csGuideBarTitle');
        var modalFooter = modal.querySelector('.cs-modal-footer');
        if (!el || !modal) return;

        function compactNav() {
            return window.innerWidth <= 991;
        }

        function menuMobileBottomOffset() {
            return window.innerWidth <= 520
                ? 'calc(5.25rem + env(safe-area-inset-bottom, 0px))'
                : 'calc(5.75rem + env(safe-area-inset-bottom, 0px))';
        }

        function resetWidgetToCorner() {
            el.style.left = 'auto';
            el.style.top = 'auto';
            el.style.right = '0';
            el.style.bottom = compactNav() ? menuMobileBottomOffset() : '0';
        }
        resetWidgetToCorner();

        function closeRegisterGuide() {
            modal.querySelectorAll('.cs-register-guide').forEach(function (g) {
                g.classList.remove('is-open');
                g.setAttribute('aria-hidden', 'true');
            });
            if (headerBar) headerBar.classList.remove('is-guide-mode');
            if (guideBar) guideBar.setAttribute('aria-hidden', 'true');
            if (modalFooter) modalFooter.classList.remove('is-hidden-for-guide');
            if (guideTitle) guideTitle.textContent = 'Support';
        }

        function openAnyGuide(activeGuide, barTitle) {
            closeRegisterGuide();
            if (activeGuide) {
                activeGuide.classList.add('is-open');
                activeGuide.setAttribute('aria-hidden', 'false');
                var sc = activeGuide.querySelector('.cs-guide-scroll');
                if (sc) sc.scrollTop = 0;
            }
            if (headerBar) headerBar.classList.add('is-guide-mode');
            if (guideBar) guideBar.setAttribute('aria-hidden', 'false');
            if (modalFooter) modalFooter.classList.add('is-hidden-for-guide');
            if (guideTitle) guideTitle.textContent = barTitle;
            if (guideBackBtn) guideBackBtn.focus();
        }

        function openRegisterGuide() {
            openAnyGuide(registerGuide, 'How to register');
        }

        function openEmailHelpGuide() {
            openAnyGuide(emailHelpGuide, 'How to create Gmail Account');
        }

        function openApplyGuide() {
            openAnyGuide(applyGuide, 'Apply for a market slot');
        }

        function openLoginGuide() {
            openAnyGuide(loginGuide, 'Log in to your account');
        }

        function openReceiptGuide() {
            openAnyGuide(receiptGuide, 'Send receipt');
        }

        function openRenewalGuide() {
            openAnyGuide(renewalGuide, 'Renew your clearance');
        }

        var pointerDown = false;
        var isDragging = false;
        var dragMoved = false;
        var startX, startY, origLeft, origTop;
        var DRAG_THRESHOLD = 10;

        function clientPoint(e) {
            if (e.touches && e.touches.length) {
                return { x: e.touches[0].clientX, y: e.touches[0].clientY };
            }
            return { x: e.clientX, y: e.clientY };
        }

        function ensurePixelPosition() {
            var p = el.getBoundingClientRect();
            origLeft = Math.round(p.left);
            origTop = Math.round(p.top);
            el.style.left = origLeft + 'px';
            el.style.top = origTop + 'px';
            el.style.right = 'auto';
            el.style.bottom = 'auto';
        }

        function onDown(e) {
            pointerDown = true;
            isDragging = false;
            dragMoved = false;
            var pt = clientPoint(e);
            startX = pt.x;
            startY = pt.y;
            var p = el.getBoundingClientRect();
            origLeft = Math.round(p.left);
            origTop = Math.round(p.top);
        }

        function onMove(e) {
            if (!pointerDown) return;
            var pt = clientPoint(e);
            var dx = pt.x - startX;
            var dy = pt.y - startY;
            if (!isDragging && (dx * dx + dy * dy) >= DRAG_THRESHOLD * DRAG_THRESHOLD) {
                isDragging = true;
                dragMoved = true;
                ensurePixelPosition();
            }
            if (!isDragging) return;
            var nl = origLeft + (pt.x - startX);
            var nt = origTop + (pt.y - startY);
            var maxL = window.innerWidth - el.offsetWidth;
            var dockClear = compactNav() ? 92 : 0;
            var maxT = window.innerHeight - el.offsetHeight - dockClear;
            nl = Math.max(0, Math.min(nl, maxL));
            nt = Math.max(0, Math.min(nt, maxT));
            el.style.left = Math.round(nl) + 'px';
            el.style.top = Math.round(nt) + 'px';
            e.preventDefault();
        }

        function openModal() {
            closeRegisterGuide();
            var supportTab = modal.querySelector('[data-cs-tab="support"]');
            if (supportTab) supportTab.click();
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            if (closeBtn) closeBtn.focus();
        }

        function closeModal() {
            closeRegisterGuide();
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function onUp(e) {
            if (!pointerDown) return;
            pointerDown = false;
            if (!dragMoved && !isDragging) {
                openModal();
                if (e.type === 'touchend') e.preventDefault();
            }
            isDragging = false;
            dragMoved = false;
        }

        el.addEventListener('mousedown', onDown);
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        el.addEventListener('touchstart', onDown, { passive: true });
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onUp, { passive: false });
        document.addEventListener('touchcancel', onUp);

        window.addEventListener('resize', function () {
            if (el.style.left === 'auto' || el.style.left === '') {
                resetWidgetToCorner();
            }
        });

        el.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                openModal();
            }
        });

        if (overlay) overlay.addEventListener('click', closeModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (guideCloseBtn) guideCloseBtn.addEventListener('click', closeModal);
        if (guideBackBtn) guideBackBtn.addEventListener('click', closeRegisterGuide);

        modal.querySelectorAll('.cs-open-register-guide').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openRegisterGuide();
            });
        });

        modal.querySelectorAll('.cs-open-email-help-guide').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openEmailHelpGuide();
            });
        });

        modal.querySelectorAll('.cs-open-apply-guide').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openApplyGuide();
            });
        });

        modal.querySelectorAll('.cs-open-login-guide').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openLoginGuide();
            });
        });

        modal.querySelectorAll('.cs-open-receipt-guide').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openReceiptGuide();
            });
        });

        modal.querySelectorAll('.cs-open-renewal-guide').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openRenewalGuide();
            });
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape' || !modal.classList.contains('is-open')) return;
            if (modal.querySelector('.cs-register-guide.is-open')) {
                closeRegisterGuide();
                ev.preventDefault();
            } else {
                closeModal();
            }
        });

        modal.querySelectorAll('[data-cs-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                closeRegisterGuide();
                var name = tab.getAttribute('data-cs-tab');
                modal.querySelectorAll('[data-cs-tab]').forEach(function (t) {
                    var on = t === tab;
                    t.classList.toggle('is-active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                modal.querySelectorAll('[data-cs-panel]').forEach(function (panel) {
                    var on = panel.getAttribute('data-cs-panel') === name;
                    panel.classList.toggle('is-active', on);
                });
            });
        });

        modal.querySelectorAll('a[data-cs-close-modal="1"]').forEach(function (a) {
            a.addEventListener('click', function () {
                closeModal();
            });
        });

        var concernForm = document.getElementById('csConcernForm');
        var concernFeedback = document.getElementById('csConcernFeedback');
        var concernSubmit = document.getElementById('csConcernSubmit');
        var concernUrl = <?php echo json_encode($cs_concern_action, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        if (concernForm && concernUrl) {
            concernForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var ta = document.getElementById('csConcernMessage');
                var msg = ta && ta.value ? ta.value.trim() : '';
                if (!msg) {
                    if (concernFeedback) {
                        concernFeedback.textContent = 'Please enter your message.';
                        concernFeedback.className = 'cs-concern-feedback is-error';
                    }
                    return;
                }
                if (concernFeedback) {
                    concernFeedback.textContent = '';
                    concernFeedback.className = 'cs-concern-feedback';
                }
                if (concernSubmit) concernSubmit.disabled = true;
                var fd = new FormData();
                fd.append('message', msg);
                fetch(concernUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json().catch(function () {
                            return { success: false, message: 'Something went wrong.' };
                        });
                    })
                    .then(function (data) {
                        if (concernSubmit) concernSubmit.disabled = false;
                        if (!concernFeedback) return;
                        if (data && data.success) {
                            concernFeedback.textContent = (data.message) || 'Thank you. We received your message.';
                            concernFeedback.className = 'cs-concern-feedback is-success';
                            if (ta) ta.value = '';
                        } else {
                            concernFeedback.textContent = (data && data.message) || 'Could not send. Please try again.';
                            concernFeedback.className = 'cs-concern-feedback is-error';
                        }
                    })
                    .catch(function () {
                        if (concernSubmit) concernSubmit.disabled = false;
                        if (concernFeedback) {
                            concernFeedback.textContent = 'Could not send. Please try again.';
                            concernFeedback.className = 'cs-concern-feedback is-error';
                        }
                    });
            });
        }
    })();
    </script>
