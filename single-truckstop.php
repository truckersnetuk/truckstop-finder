<?php
if (!defined('ABSPATH')) exit;
get_header();

global $post;
$payload = TSF_Helpers::listing_payload($post);
$details = method_exists('TSF_Helpers', 'get_details') ? TSF_Helpers::get_details($post->ID) : [];
$reviews = TSF_Helpers::approved_reviews($post->ID);
$photos = TSF_Helpers::approved_photos($post->ID);
$nearby = method_exists('TSF_Helpers', 'nearby_listings') ? TSF_Helpers::nearby_listings($post->ID) : [];

$title = get_the_title($post);
$town = isset($payload['town_city']) ? trim((string)$payload['town_city']) : '';
$postcode = isset($payload['postcode']) ? trim((string)$payload['postcode']) : '';
$location_line = implode(' • ', array_values(array_filter([$town, $postcode])));

$rating = isset($payload['rating']) ? (float)$payload['rating'] : 0;
$rating_count = isset($payload['rating_count']) ? (int)$payload['rating_count'] : 0;
$trust_label = !empty($payload['trust_label']) ? $payload['trust_label'] : 'New listing';

$details = is_array($details) ? $details : [];
$reviews = is_array($reviews) ? $reviews : [];

$quick_facts = [
    'Town' => !empty($town) ? $town : '',
    'Postcode' => !empty($postcode) ? $postcode : '',
    'Opening hours' => !empty($details['opening_hours']) && $details['opening_hours'] !== 'None' ? $details['opening_hours'] : '',
    'Parking type' => !empty($details['parking_type']) && $details['parking_type'] !== 'None' ? $details['parking_type'] : '',
    'Night price' => ($details['price_night'] ?? '') !== '' ? '£' . $details['price_night'] : '',
];

$facility_map = [
    'showers' => 'Showers',
    'secure_parking' => 'Secure parking',
    'overnight_parking' => 'Overnight parking',
    'fuel' => 'Fuel',
    'food' => 'Food',
    'toilets' => 'Toilets',
];
$facilities = [];
foreach ($facility_map as $key => $label) {
    if (!empty($payload[$key])) $facilities[] = $label;
}

$summary_text = trim((string) wp_strip_all_tags(get_the_excerpt($post)));
if (!$summary_text) {
    $summary_text = 'Found wrong info? Use the suggestion tools in the finder to improve this listing instead of creating a duplicate.';
}

function tsf_single_safe($value) {
    $value = is_string($value) ? trim($value) : $value;
    if ($value === null || $value === '' || $value === 'None') return '';
    return $value;
}

$directions_url = '';
if (!empty($payload['lat']) && !empty($payload['lng'])) {
    $directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($payload['lat'] . ',' . $payload['lng']);
} elseif ($location_line) {
    $directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(trim($title . ', ' . $location_line));
}
?>
<style>
.tsf-single-wrap{max-width:920px;margin:0 auto;padding:24px 18px 96px;color:#0f172a}
.tsf-single-card{background:#fff;border:1px solid rgba(148,163,184,.18);border-radius:24px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
.tsf-single-hero{padding:24px 22px 18px}
.tsf-single-title{margin:0;font-size:clamp(34px,7vw,56px);line-height:1.02;letter-spacing:.02em;font-weight:800;color:#3a3955;text-transform:uppercase}
.tsf-single-sub{margin-top:10px;font-size:16px;color:#64748b}
.tsf-single-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.tsf-pill{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:#f8fafc;border:1px solid rgba(148,163,184,.18);font-size:14px;color:#334155}
.tsf-single-section{padding:22px;border-top:1px solid rgba(148,163,184,.15)}
.tsf-single-section h2{margin:0 0 14px;font-size:22px;line-height:1.1;letter-spacing:.03em;text-transform:uppercase;color:#3a3955}
.tsf-single-copy{font-size:17px;line-height:1.6;color:#475569}
.tsf-facts{margin:0;padding-left:22px}
.tsf-facts li{margin:0 0 10px;font-size:17px;line-height:1.5;color:#475569}
.tsf-facts strong{color:#0f172a}
.tsf-action-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
.tsf-btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:12px 18px;border-radius:16px;border:1px solid rgba(148,163,184,.22);background:#fff;color:#0f172a;text-decoration:none;font-weight:600}
.tsf-btn-primary{background:#0f172a;color:#fff;border-color:#0f172a}
.tsf-grid{display:grid;grid-template-columns:1fr;gap:16px}
.tsf-review{padding:14px 0;border-top:1px solid rgba(148,163,184,.15)}
.tsf-review:first-child{border-top:0;padding-top:0}
.tsf-muted{color:#64748b}
.tsf-nearby-list{display:grid;gap:12px}
.tsf-nearby-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;border-radius:18px;background:#fff;border:1px solid rgba(148,163,184,.18)}
@media (min-width:768px){
  .tsf-single-wrap{padding:32px 24px 110px}
  .tsf-grid{grid-template-columns:1.05fr .95fr}
}
</style>

<div class="tsf-single-wrap">
  <article class="tsf-single-card">
    <section class="tsf-single-hero">
      <h1 class="tsf-single-title"><?php echo esc_html($title); ?></h1>
      <?php if ($location_line) : ?>
        <div class="tsf-single-sub"><?php echo esc_html($location_line); ?></div>
      <?php endif; ?>

      <div class="tsf-single-meta">
        <span class="tsf-pill"><?php echo esc_html('★ ' . number_format_i18n($rating, 1) . ' (' . $rating_count . ')'); ?></span>
        <span class="tsf-pill"><?php echo esc_html($trust_label); ?></span>
        <?php if (!empty($payload['featured'])) : ?><span class="tsf-pill">Featured</span><?php endif; ?>
        <?php foreach (array_slice($facilities, 0, 4) as $facility) : ?>
          <span class="tsf-pill"><?php echo esc_html($facility); ?></span>
        <?php endforeach; ?>
      </div>

      <div class="tsf-action-row">
        <?php if ($directions_url) : ?>
          <a class="tsf-btn" href="<?php echo esc_url($directions_url); ?>" target="_blank" rel="noopener noreferrer">Navigate</a>
        <?php endif; ?>
        <a class="tsf-btn tsf-btn-primary" href="<?php echo esc_url(home_url('/truckstop-finder/')); ?>">Back to finder</a>
      </div>
    </section>

    <div class="tsf-grid">
      <section class="tsf-single-section">
        <h2>About this stop</h2>
        <div class="tsf-single-copy"><?php echo esc_html($summary_text); ?></div>

        <h2 style="margin-top:26px;">Quick facts</h2>
        <ul class="tsf-facts">
          <?php foreach ($quick_facts as $label => $value) : $safe = tsf_single_safe($value); if (!$safe) continue; ?>
            <li><strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($safe); ?></li>
          <?php endforeach; ?>
          <?php if (!$quick_facts['Town'] && !$quick_facts['Postcode'] && !$quick_facts['Opening hours'] && !$quick_facts['Parking type'] && !$quick_facts['Night price']) : ?>
            <li>No quick facts have been added yet.</li>
          <?php endif; ?>
        </ul>

        <h2 style="margin-top:26px;">Facilities</h2>
        <?php if ($facilities) : ?>
          <div class="tsf-single-meta">
            <?php foreach ($facilities as $facility) : ?>
              <span class="tsf-pill"><?php echo esc_html($facility); ?></span>
            <?php endforeach; ?>
          </div>
        <?php else : ?>
          <div class="tsf-muted">No facilities have been added yet.</div>
        <?php endif; ?>
      </section>

      <section class="tsf-single-section">
        <h2>Reviews</h2>
        <div class="tsf-muted" style="margin-bottom:14px;">All reviews are posted instantly. Content may be audited and removed if necessary.</div>
        <?php if (!empty($reviews)) : ?>
          <?php foreach ($reviews as $review) : ?>
            <div class="tsf-review">
              <strong><?php echo esc_html('★ ' . number_format_i18n((float)($review['rating'] ?? 0), 1) . (!empty($review['author_name']) ? ' • ' . $review['author_name'] : ' • Driver')); ?></strong>
              <?php if (!empty($review['review_text'])) : ?>
                <div class="tsf-single-copy" style="font-size:16px;"><?php echo esc_html($review['review_text']); ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else : ?>
          <div class="tsf-muted">No reviews yet — be the first to help other drivers.</div>
        <?php endif; ?>

        <h2 style="margin-top:26px;">Photos</h2>
        <?php if (!empty($photos)) : ?>
          <div class="tsf-muted"><?php echo esc_html(count($photos) . ' photo' . (count($photos) === 1 ? '' : 's') . ' available.'); ?></div>
        <?php else : ?>
          <div class="tsf-muted">No approved photos yet.</div>
        <?php endif; ?>

        <?php if (!empty($nearby)) : ?>
          <h2 style="margin-top:26px;">Nearby alternatives</h2>
          <div class="tsf-nearby-list">
            <?php foreach (array_slice($nearby, 0, 4) as $item) : ?>
              <div class="tsf-nearby-item">
                <div>
                  <strong><?php echo esc_html($item['title']); ?></strong>
                  <div class="tsf-muted"><?php echo esc_html(implode(' • ', array_filter([$item['town_city'] ?? '', $item['postcode'] ?? '']))); ?></div>
                </div>
                <a class="tsf-btn" href="<?php echo esc_url($item['url']); ?>">Open</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </article>
</div>

<?php get_footer(); ?>
