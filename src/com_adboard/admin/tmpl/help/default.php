<?php
\defined('_JEXEC') or die;
/**
 * Ad Board help page (admin popup, tmpl=component).
 *
 * Opens inside a Joomla Bootstrap-enabled popup so core styles are already
 * loaded.  Any component-specific overrides use the .ab-help- prefix.
 */
$cfg = [
    'maxImages'       => $this->maxImages,
    'maxImageSizeMb'  => $this->maxImageSizeMb,
    'rateLimitMax'    => $this->rateLimitMax,
    'rateLimitWindow' => $this->rateLimitWindow,
];
?>
<style>
/*
 * Hard-coded dark-theme palette.
 *
 * We cannot rely on --bs-body-color in tmpl=component popup context because
 * Joomla resolves it to Bootstrap's light-mode default (#212529) even inside
 * a dark Atum admin skin.  All colours below are therefore explicit.
 *
 * CSS variables are kept only as optional enhancement for custom themes.
 */

/* ── Palette ──────────────────────────────────────────────────────── */
:root {
    --ab-bg:        #1e2030;   /* page background                    */
    --ab-bg2:       #272a3d;   /* cards / TOC / table header         */
    --ab-bg3:       #2f3347;   /* alternating table rows             */
    --ab-border:    #3e4259;   /* all borders                        */
    --ab-text:      #cdd6f4;   /* primary text   — always light      */
    --ab-text-dim:  #a6adc8;   /* secondary text — slightly dimmed   */
    --ab-accent:    #89b4fa;   /* links / accents                    */
    --ab-heading:   #f5f5f5;   /* headings                           */
    --ab-code-bg:   #313244;   /* inline code background             */
}

/* ── Page ─────────────────────────────────────────────────────────── */
html, body {
    margin: 0; padding: 0;
    background: var(--ab-bg);
    color:      var(--ab-text);
    font-size:  .94rem;
    line-height: 1.65;
}
a       { color: var(--ab-accent); }
a:hover { color: #b4d0ff; }
strong  { color: var(--ab-heading); }
code    { font-size: .85rem; background: var(--ab-code-bg);
          border: 1px solid var(--ab-border); border-radius: 3px;
          padding: 1px 5px; color: var(--ab-text); }

/* ── Layout wrapper ───────────────────────────────────────────────── */
.ab-help-body {
    padding:   24px 32px 48px;
    max-width: 860px;
}

/* ── Title / lead ─────────────────────────────────────────────────── */
.ab-help-h1   {
    font-size: 1.45rem; font-weight: 700; margin-bottom: 4px;
    color: var(--ab-heading);
}
.ab-help-lead { color: var(--ab-text-dim); margin-bottom: 28px; }

/* ── Table of contents box ────────────────────────────────────────── */
.ab-help-toc {
    background:    var(--ab-bg2);
    border-left:   4px solid var(--ab-accent);
    border-radius: 5px;
    padding:       16px 22px;
    margin-bottom: 32px;
}
.ab-help-toc h6 {
    font-size: .75rem; text-transform: uppercase; letter-spacing: .6px;
    color: var(--ab-text-dim); margin-bottom: 10px;
}
.ab-help-toc ol { margin: 0; padding-left: 18px; }
.ab-help-toc li { margin-bottom: 3px; }
.ab-help-toc a  { color: var(--ab-accent); text-decoration: none; }
.ab-help-toc a:hover { text-decoration: underline; }

/* ── Sections ─────────────────────────────────────────────────────── */
.ab-help-section     { margin-bottom: 36px; }
.ab-help-section h2  {
    font-size: 1.1rem; font-weight: 700;
    border-bottom: 2px solid var(--ab-border);
    padding-bottom: 7px; margin-bottom: 16px;
    color: var(--ab-heading);
}
.ab-help-section h3  {
    font-size: 1rem; font-weight: 600;
    margin: 20px 0 8px;
    color: var(--ab-heading);
}
p, li { color: var(--ab-text); }

/* ── Data tables ──────────────────────────────────────────────────── */
.ab-help-table {
    width: 100%; border-collapse: collapse;
    margin: 10px 0 18px; font-size: .88rem;
}
.ab-help-table th {
    background:  var(--ab-bg2);
    color:       var(--ab-heading);
    font-weight: 600;
    padding:     8px 12px;
    border:      1px solid var(--ab-border);
    text-align:  left;
}
.ab-help-table td {
    padding: 7px 12px;
    border:  1px solid var(--ab-border);
    vertical-align: top;
    color:   var(--ab-text);
}
.ab-help-table tr:nth-child(even) td { background: var(--ab-bg3); }

/* ── Inline badges ────────────────────────────────────────────────── */
.ab-help-badge {
    display: inline-block; font-size: .78rem; font-weight: 600;
    padding: 2px 9px; border-radius: 4px; border: 1px solid transparent;
}
.ab-help-badge-warn {
    background: rgba(249,226,175,.15);
    color:      #f9e2af;
    border-color: rgba(249,226,175,.3);
}
.ab-help-badge-ok {
    background: rgba(166,227,161,.15);
    color:      #a6e3a1;
    border-color: rgba(166,227,161,.3);
}
.ab-help-badge-info {
    background:   rgba(243,139,168,.15);
    color:        #f38ba8;
    border-color: rgba(243,139,168,.3);
}

/* ── Monospace config snippets ────────────────────────────────────── */
.ab-help-cfg {
    font-family: monospace; font-size: .85rem;
    background:  var(--ab-code-bg);
    color:       var(--ab-text);
    border:      1px solid var(--ab-border);
    padding: 1px 6px; border-radius: 3px;
}
</style>

<div class="ab-help-body">

    <div class="ab-help-h1">🗒 Ad Board — User Guide</div>
    <div class="ab-help-lead">
        A moderated classified advertisement board for Joomla. Site visitors submit ads; administrators
        review and publish them. Version 1.0 &nbsp;·&nbsp; com_adboard
    </div>

    <!-- ── Table of contents ──────────────────────────────────────────── -->
    <div class="ab-help-toc">
        <h6>Contents</h6>
        <ol>
            <li><a href="#sec-flow">How it works — end-to-end flow</a></li>
            <li><a href="#sec-visitors">For site visitors</a></li>
            <li><a href="#sec-moderators">For moderators</a></li>
            <li><a href="#sec-admins">For administrators</a></li>
            <li><a href="#sec-config">Configuration reference (Options)</a></li>
            <li><a href="#sec-acl">User permissions (ACL)</a></li>
            <li><a href="#sec-menu">Creating menu items</a></li>
            <li><a href="#sec-expired">Expired advertisements</a></li>
            <li><a href="#sec-email">Email notifications</a></li>
        </ol>
    </div>

    <!-- ── 1. Flow ────────────────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-flow">
        <h2>1 &nbsp; How it works — end-to-end flow</h2>
        <ol>
            <li>A visitor fills in the <strong>Post an Ad</strong> form on the site.</li>
            <li>The ad is saved with state <span class="ab-help-badge ab-help-badge-warn">Pending</span> and the site admin receives a notification email.</li>
            <li>An administrator or moderator opens the <strong>Ad Board</strong> in the backend, reviews the ad, and either
                <span class="ab-help-badge ab-help-badge-ok">Publishes</span> or
                <span class="ab-help-badge ab-help-badge-info" style="background:#f8d7da;color:#58151c">Rejects</span> it.</li>
            <li>On first approval Joomla stamps <em>publish_up = now</em> and recalculates <em>publish_down</em> so the full requested duration starts from the moment of approval (days spent in moderation queue are not wasted).</li>
            <li>The ad is live until it reaches its expiry date; it then disappears from the public listing automatically.</li>
        </ol>
    </div>

    <!-- ── 2. Visitors ───────────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-visitors">
        <h2>2 &nbsp; For site visitors</h2>

        <h3>Browsing the board</h3>
        <p>The listing page shows all published, non-expired ads as cards with a cover image (if any),
        title, category badge, description excerpt, and publication / expiry dates.
        Visitors can filter by free-text search (title) and by category.</p>

        <h3>Viewing a single ad</h3>
        <p>Clicking a card opens the ad detail page with the full image gallery
        (click the main photo or use arrow keys to advance), full description, and contact details.</p>

        <h3>Submitting an ad</h3>
        <ol>
            <li>Click <strong>Post an Ad</strong> (top-right of the listing, or via a menu item).</li>
            <li>Fill in: <strong>Title</strong> (required), <strong>Category</strong> (required),
                <strong>Description</strong> (optional), <strong>Contact details</strong> (required).</li>
            <li>Optionally add up to <strong class="ab-help-cfg"><?= $cfg['maxImages'] ?></strong> photos
                (JPG / PNG / WebP / GIF, max <strong class="ab-help-cfg"><?= $cfg['maxImageSizeMb'] ?> MB</strong> and 12 MP each).
                The <em>first</em> photo becomes the listing cover image.</li>
            <li>Choose how long the ad should run: the available durations are set in
                <strong>Options → Expiry Terms</strong>.</li>
            <li>Click <strong>Submit for Review</strong>.</li>
        </ol>
        <p>The ad enters moderation and will not appear publicly until approved.
        The submission form is public — no login is required.</p>

        <p><strong>Spam protection:</strong> no more than
        <strong class="ab-help-cfg"><?= $cfg['rateLimitMax'] ?></strong> ads
        per IP in any <strong class="ab-help-cfg"><?= $cfg['rateLimitWindow'] ?></strong>-minute window.
        These limits are configurable in Options → Security.</p>
    </div>

    <!-- ── 3. Moderators ─────────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-moderators">
        <h2>3 &nbsp; For moderators</h2>
        <p>Moderators have access to the full Ad Board admin section except the Options panel.</p>

        <h3>The ad list</h3>
        <p>Shows all ads regardless of state. Filter controls at the top let you narrow by
        free-text, status, and category. The <strong>Pending</strong> filter is the typical
        starting point for a moderation workflow.</p>

        <table class="ab-help-table">
            <tr><th>Column</th><th>Meaning</th></tr>
            <tr><td>Submitted</td><td>When the site visitor submitted the ad.</td></tr>
            <tr><td>Published</td><td>When the admin first approved it (<em>publish_up</em>).</td></tr>
            <tr><td>Expires</td>  <td>Calculated expiry date (<em>publish_down</em>). Ads highlighted in red have passed their expiry while still marked Published — update the date before re-publishing.</td></tr>
            <tr><td>👁 Views</td> <td>Public page views since publication.</td></tr>
        </table>

        <h3>Bulk actions (toolbar buttons)</h3>
        <p>Tick one or more checkboxes then click a toolbar button:</p>
        <table class="ab-help-table">
            <tr><th>Button</th><th>Result</th><th>Permission needed</th></tr>
            <tr><td><strong>Publish</strong></td>   <td>Sets state to Published. Stamps <em>publish_up = now</em>. On first approval also recalculates <em>publish_down</em>.</td><td><span class="ab-help-cfg">core.edit.state</span></td></tr>
            <tr><td><strong>Unpublish</strong></td> <td>Returns ad to Pending state (can be re-published later).</td><td><span class="ab-help-cfg">core.edit.state</span></td></tr>
            <tr><td><strong>Reject</strong></td>    <td>Marks ad as Rejected (hidden from public, stays in list).</td><td><span class="ab-help-cfg">core.edit.state</span></td></tr>
            <tr><td><strong>Delete</strong></td>    <td>Permanently removes the ad and all its image files from disk.</td><td><span class="ab-help-cfg">core.delete</span></td></tr>
        </table>

        <h3>Editing an ad</h3>
        <p>Click the ad title to open the edit form. You can change all fields,
        update the expiry date, add or remove photos, and change the state.
        The sidebar shows the original submission date and submitter IP (for spam tracking).</p>

        <p><span class="ab-help-badge ab-help-badge-warn">⚠ Expired ad warning:</span>
        If an ad is currently Published but its expiry date is in the past, a yellow banner
        appears in the edit form reminding you to update the date before re-publishing.</p>
    </div>

    <!-- ── 4. Admins ─────────────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-admins">
        <h2>4 &nbsp; For administrators</h2>
        <p>Administrators have all moderator capabilities plus access to <strong>Options</strong>
        (the component configuration panel).</p>

        <h3>Creating an ad from the backend</h3>
        <p>Click <strong>New Advertisement</strong> in the toolbar. Backend-created ads can be
        published immediately by choosing <em>Published</em> in the Status dropdown.
        No notification email is sent for backend-created ads.</p>
    </div>

    <!-- ── 5. Configuration ──────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-config">
        <h2>5 &nbsp; Configuration reference (Options)</h2>
        <p>Open via <strong>Components → Ad Board</strong> → toolbar button <strong>Options</strong>.
        Requires <span class="ab-help-cfg">core.admin</span> permission.</p>

        <h3>General tab</h3>
        <p>Contains two sections:</p>
        <table class="ab-help-table">
            <tr><th style="width:35%">Setting</th><th>Description</th></tr>
            <tr><td colspan="2" style="font-weight:600;background:rgba(255,255,255,.04)">Submission</td></tr>
            <tr>
                <td>Redirect after submission</td>
                <td>The menu item users are sent to after successfully submitting an ad.
                Leave empty to redirect to the ad board listing.</td>
            </tr>
            <tr><td colspan="2" style="font-weight:600;background:rgba(255,255,255,.04)">Notifications</td></tr>
            <tr>
                <td>Notify moderator on new submission</td>
                <td>When <strong>Yes</strong> (default), an email is sent to the address configured in
                Global Configuration → Server → From Email whenever a visitor submits an ad via the
                public form. Set to <strong>No</strong> to disable all submission emails without affecting anything else.</td>
            </tr>
        </table>

        <h3>Categories tab</h3>
        <p>Define the ad categories shown in the submission form and in the listing filter.
        Each category has:</p>
        <ul>
            <li><strong>Slug</strong> — internal identifier stored in the database (e.g. <span class="ab-help-cfg">items</span>).
            <em>Never change this after ads have been submitted</em> — existing ads reference the slug.</li>
            <li><strong>Title (EN)</strong> — default display name for any language without its own field.</li>
            <li><strong>Title (pl-PL)</strong> — Polish display name.</li>
        </ul>
        <p>Click <strong>+</strong> to add a row; click <strong>−</strong> to remove one.
        Slugs are auto-generated from the title on first save (diacritics are transliterated).</p>

        <h3>Expiry Terms tab</h3>
        <p>Define the "how long should the ad run?" options presented in the submission form.
        Each row has a number of <strong>Days</strong> and display labels for EN and pl-PL.
        At least one row is required; four defaults (7, 14, 21, 30 days) are seeded on install.</p>

        <h3>Security tab</h3>
        <table class="ab-help-table">
            <tr><th style="width:35%">Setting</th><th>Default</th><th>Description</th></tr>
            <tr><td>Max image size (MB)</td><td><?= $cfg['maxImageSizeMb'] ?></td><td>Per-file upload size limit.</td></tr>
            <tr><td>Max images per ad</td>  <td><?= $cfg['maxImages'] ?></td>    <td>How many photos one ad may have.</td></tr>
            <tr><td>Max submissions per window</td><td><?= $cfg['rateLimitMax'] ?></td><td>IP-level rate limit count.</td></tr>
            <tr><td>Time window (minutes)</td><td><?= $cfg['rateLimitWindow'] ?></td><td>Sliding window for the rate limit.</td></tr>
        </table>
        <p>Images are always re-encoded via PHP's GD library (strips EXIF data and embedded payloads)
        and resized to a maximum of 1920 px on any side before saving to disk.</p>
    </div>

    <!-- ── 6. Permissions ────────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-acl">
        <h2>6 &nbsp; User permissions (ACL)</h2>
        <p>Configure via <strong>Users → Groups</strong> → select a group → <em>Ad Board</em> section.
        This works identically to any other Joomla component.</p>

        <table class="ab-help-table">
            <tr><th style="width:30%">Permission</th><th>What it controls</th></tr>
            <tr><td><span class="ab-help-cfg">core.manage</span></td>    <td>Entry gate — can open the Ad Board admin section at all.</td></tr>
            <tr><td><span class="ab-help-cfg">core.create</span></td>    <td>Can click <em>New Advertisement</em> and save new ads from the backend.</td></tr>
            <tr><td><span class="ab-help-cfg">core.edit</span></td>      <td>Can open and save changes to existing ads.</td></tr>
            <tr><td><span class="ab-help-cfg">core.edit.state</span></td><td>Can Publish, Unpublish, and Reject ads.</td></tr>
            <tr><td><span class="ab-help-cfg">core.delete</span></td>    <td>Can permanently delete ads and their image files.</td></tr>
            <tr><td><span class="ab-help-cfg">core.admin</span></td>     <td>Can open the Options configuration panel. Grants all of the above implicitly.</td></tr>
        </table>

        <h3>Suggested group setup</h3>
        <table class="ab-help-table">
            <tr><th>Role</th><th>Permissions to grant</th></tr>
            <tr><td><strong>Moderator</strong></td><td><span class="ab-help-cfg">core.manage</span>, <span class="ab-help-cfg">core.create</span>, <span class="ab-help-cfg">core.edit</span>, <span class="ab-help-cfg">core.edit.state</span>, <span class="ab-help-cfg">core.delete</span></td></tr>
            <tr><td><strong>Admin</strong></td>    <td>All of the above + <span class="ab-help-cfg">core.admin</span></td></tr>
        </table>
    </div>

    <!-- ── 7. Menu items ─────────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-menu">
        <h2>7 &nbsp; Creating menu items</h2>
        <p>Go to <strong>Menus → [your menu] → Add New Menu Item</strong> → click
        <strong>Select</strong> under <em>Menu Item Type</em> → expand the
        <strong>Ad Board</strong> group. Two types are available:</p>

        <table class="ab-help-table">
            <tr><th>Type</th><th>Displays</th></tr>
            <tr><td><strong>Ad Board — Listings</strong></td><td>The public ad listing with search and category filter.</td></tr>
            <tr><td><strong>Ad Board — Post an Ad</strong></td><td>The public ad submission form.</td></tr>
        </table>

        <p>Tip: you can use the <em>Listings</em> menu item as the target for the
        <em>Redirect after submission</em> Options setting so users land back on the board
        after submitting.</p>
    </div>

    <!-- ── 8. Expired state ──────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-expired">
        <h2>8 &nbsp; Expired advertisements</h2>

        <p>An ad automatically transitions to
        <span class="ab-help-badge" style="background:rgba(108,117,125,.25);color:var(--ab-text,#cdd6f4)">Expired</span>
        when its <em>Expires</em> date passes. The transition is lazy — it fires on the next
        page load (admin list or public board) via a single <code>UPDATE</code>.</p>

        <h3>What changes</h3>
        <ul>
            <li>The ad disappears from the public listing immediately (the site query always
                adds a <code>publish_down ≥ NOW()</code> belt-and-braces filter).</li>
            <li>In the admin list the Status column shows <strong>Expired</strong> (grey badge).</li>
            <li>Expired ads are filterable — select <em>Expired</em> in the Status filter.</li>
        </ul>

        <h3>Re-publishing an expired ad</h3>
        <p>Re-publishing requires two deliberate steps:</p>
        <ol>
            <li>Open the ad in the edit form.</li>
            <li>Update <strong>Expires</strong> to a future date.</li>
            <li>Change <strong>Status</strong> to <em>Published</em> and Save.</li>
        </ol>
        <p>If you try to publish (from the list or the edit form) while the expiry date is
        still in the past, the action is <strong>blocked</strong> and a warning is shown.
        Update the date first.</p>

        <h3>There is no manual "Expire" action</h3>
        <p>You cannot set an ad to Expired manually. To retire an ad before its natural expiry
        use <strong>Reject</strong> — it hides the ad from the public listing and keeps it in
        the admin archive.</p>
    </div>

    <!-- ── 9. Email ──────────────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-email">
        <h2>9 &nbsp; Email notifications</h2>
        <p>When a visitor submits an ad through the site form, a plain-text notification email
        is sent to the address configured in
        <strong>System → Global Configuration → Server → Mail → From email</strong>.
        The email contains the full title, category, description, and contact information.</p>

        <h3>Enabling / disabling</h3>
        <p>Go to <strong>Components → Ad Board → Options → Generic tab → Email notifications</strong>
        and toggle <em>Notify moderator on new submission</em>. The setting is <strong>enabled
        by default</strong>. Disabling it stops all submission emails without affecting anything else.</p>

        <table class="ab-help-table">
            <tr><th style="width:35%">Condition</th><th>Result</th></tr>
            <tr><td>Toggle = <strong>Yes</strong> (default)</td><td>Email sent on every public form submission</td></tr>
            <tr><td>Toggle = <strong>No</strong></td><td>No email sent — ad is still saved normally</td></tr>
            <tr><td>No From address configured</td><td>Email silently skipped regardless of toggle</td></tr>
            <tr><td>Mail server unreachable</td><td>Email silently skipped — ad is still saved</td></tr>
        </table>

        <p>No email is ever sent when an ad is created directly from the admin backend.</p>
    </div>

    <!-- ── 10. Smart Search ─────────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-finder">
        <h2>10 &nbsp; Smart Search integration</h2>
        <p>Ad Board ships with a bundled Finder plugin (<code>plg_finder_adboard</code>)
        that indexes published, non-expired advertisements into Joomla's Smart Search
        index so they appear alongside articles and other content in the site's global
        search bar.</p>

        <p>The finder plugin (<code>plg_finder_adboard</code>) ships as part of the
        <strong>Ad Board — Full Package</strong> ZIP and is installed automatically.</p>

        <h3>First-time setup</h3>
        <ol>
            <li>Go to <strong>System → Plugins</strong> — filter by <em>Type = finder</em>.</li>
            <li>Find <strong>Smart Search – Ad Board</strong> and enable it (green tick).</li>
            <li>Go to <strong>Components → Smart Search</strong> and click
                <strong>Index</strong> in the toolbar.</li>
            <li>Wait for the progress bar — ads will now appear in site search results and
            in the Advanced Search type filter.</li>
        </ol>

        <h3>What gets indexed</h3>
        <p>Only <strong>Published</strong> ads whose expiry date is in the future are
        indexed. Expired, Pending, and Rejected ads are never in the index.</p>

        <h3>Search result URLs</h3>
        <p>Search results link directly to the friendly URL of the ad
        (e.g. <code>/ogloszenia/12</code>) provided at least one published site menu item
        pointing to <strong>Ad Board</strong> exists. The plugin resolves the menu item
        automatically at index time — no manual configuration needed.</p>

        <h3>Keeping the index fresh</h3>
        <p>The index does not update in real-time. Re-run <strong>Index</strong> from Smart
        Search after significant changes (bulk state changes, mass deletions, or after
        upgrading the component). You can also schedule the CLI indexer via Joomla's
        Scheduled Tasks:</p>
        <pre style="background:rgba(0,0,0,.3);padding:.6em .8em;border-radius:4px;font-size:.85em;overflow-x:auto">php cli/joomla.php finder:index</pre>

        <p>The garbage-collection step (automatic after each Index run) removes any ad that
        has since expired or been unpublished, so stale results are cleaned up without
        manual intervention.</p>
    </div>

    <!-- ── 12. Action logging ───────────────────────────────────────── -->
    <div class="ab-help-section" id="sec-logs">
        <h2>12 &nbsp; Action logging</h2>
        <p>Every admin action in Ad Board is recorded to Joomla's built-in action log
        (<code>com_actionlogs</code>). Entries appear in two places:</p>
        <ul>
            <li><strong>Home Dashboard → Latest Actions panel</strong> — immediately visible after any action.</li>
            <li><strong><code>administrator/index.php?option=com_actionlogs</code></strong> — full searchable log
            with date, user, and extension filters.</li>
        </ul>

        <h3>Logged events</h3>
        <table class="ab-help-table">
            <tr><th style="width:40%">Action</th><th>Example entry</th></tr>
            <tr><td>Approve / publish ad(s)</td><td>Ad Board: <em>username</em> approved / published N ad(s)</td></tr>
            <tr><td>Unpublish ad(s)</td><td>Ad Board: <em>username</em> unpublished N ad(s)</td></tr>
            <tr><td>Reject ad(s)</td><td>Ad Board: <em>username</em> rejected N ad(s)</td></tr>
            <tr><td>Delete ad(s)</td><td>Ad Board: <em>username</em> deleted N ad(s)</td></tr>
            <tr><td>Save / update ad (admin)</td><td>Ad Board: <em>username</em> updated ad "Title" (ID n)</td></tr>
            <tr><td>Create ad (admin)</td><td>Ad Board: <em>username</em> created ad "Title" (ID n)</td></tr>
        </table>
        <p>The username in each entry is a clickable link to that user's admin profile.</p>

        <h3>Smart change detection</h3>
        <p>Save / update events are only logged when the ad data <strong>actually changed</strong>.
        Opening an ad and clicking Save &amp; Close without modifying anything produces
        no log entry. The component compares an MD5 hash of the seven editable fields
        (title, category, description, contact, state, publish_up, publish_down)
        before and after saving — a log entry is written only when the hashes differ.</p>

        <p>Logging is non-fatal — if <code>com_actionlogs</code> is unavailable for any reason,
        the action still completes normally.</p>
    </div>

    <!-- ── 11. Manager / moderator access ────────────────────────────── -->
    <div class="ab-help-section" id="sec-manager">
        <h2>11 &nbsp; Granting moderator access (Manager group)</h2>
        <p>From <strong>v1.5.1</strong> onwards, installing or updating the component
        automatically grants the Joomla <em>Manager</em> group the following permissions
        for Ad Board:</p>

        <table class="ab-help-table">
            <tr><th>Permission</th><th>Granted?</th></tr>
            <tr><td><span class="ab-help-cfg">core.manage</span></td><td>✔ Allowed — can open and use the Ad Board admin section</td></tr>
            <tr><td><span class="ab-help-cfg">core.create</span></td><td>✔ Allowed</td></tr>
            <tr><td><span class="ab-help-cfg">core.edit</span></td><td>✔ Allowed</td></tr>
            <tr><td><span class="ab-help-cfg">core.edit.state</span></td><td>✔ Allowed — can approve, reject, publish, unpublish</td></tr>
            <tr><td><span class="ab-help-cfg">core.delete</span></td><td>✔ Allowed</td></tr>
            <tr><td><span class="ab-help-cfg">core.admin</span></td><td>✘ Not granted — Options screen remains Super-Users only</td></tr>
        </table>

        <p>If you need to adjust these later, go to
        <strong>Components → Ad Board → Options → Permissions</strong> tab.</p>
    </div>

</div>
