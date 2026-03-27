=== Truckstop Finder ===
Contributors: openai
Tags: truck stop, hgv, directory, maps, reviews
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later

HGV truck stop finder with map search, driver accounts, favourites, submissions, edit suggestions, photo uploads, moderation, duplicate checks, postcode/radius search, listing detail panel, photo galleries, simple account dashboard, and custom single listing template.

Shortcode:
[truckstop_finder]

Settings:
WordPress Admin -> Truckstop Finder -> Google Maps API key

CSV Import columns:
title,description,town_city,postcode,lat,lng,showers,secure_parking,overnight_parking,fuel,food,toilets,price_night,featured,opening_hours,parking_type

V1.5 adds a stronger account area, saved places integration, richer listing cards, and moderation overview counts.

V1.5 adds a better saved places flow, richer account dashboard, improved listing cards, and moderation overview counts.

V1.6 adds edit-before-approve moderation, trust/status badges, and stronger listing detail polish.

V1.7 adds a stepped add-place form, better mobile UX, improved detail actions, and a more polished account and review experience.

V1.8 adds stronger search filters, moderation notes/history, improved saved-place interactions, and more refined single listing presentation.

V1.9 adds queued email notifications, stronger admin-only moderation controls, operator-style analytics, and deeper listing trust/engagement signals.

V2.0 adds operator claims, featured listing order management, queued email notifications, and lightweight admin analytics for a monetisation-ready foundation.

V2.1 adds Stripe-ready payment settings, operator account creation on claim approval, sponsored style tiers, and a stronger operator monetisation foundation.

V2.2 adds operator login sessions, a self-service operator portal, listing edit access for approved operators, and checkout-ready featured upgrade requests.

V2.3 adds operator dashboard polish, billing history, checkout-ready upgrade links, and stronger foundations for a real payment gateway integration.

V2.4 adds webhook-ready payment completion flow, checkout session creation scaffolding, tighter operator edit handling, and stronger production-oriented billing foundations.

V2.5 fixes activation errors, adds safety guards, and improves debugging.

V2.6 is a stability rebuild that cleans up activation-critical PHP structure, cron deactivation cleanup, and safer database/payment helpers.

V2.7 adds production-hardening improvements including featured expiry maintenance, moderation tabs, tighter operator update validation, and loading-state polish.

V2.8 adds moderation status filters, operator update history, filter reset UX, stronger feature state visibility, and admin/operator quality-of-life improvements.

V3.0 is the growth-engine build: clearer add-stop flow, stronger trust signals, strict moderation messaging, improved contribution UX, and richer photo/review visibility.

V3.2 adds hard duplicate blocking for new stop submissions, clearer duplicate responses from the API, and frontend guidance to redirect drivers to the existing listing instead.

V3.3 adds nearby stop suggestions in the app and on listing pages, improving discovery and helping drivers compare alternatives within driving distance.

V3.5 adds map clustering, mobile map toggling, mapped-result stats, and a smoother nearby-comparison workflow for larger result sets.

V3.6 adds search sorting, richer nearby-stop comparison cards, and quick navigation actions to make discovery and decision-making faster for drivers.

V3.7 adds quick filter presets, a results summary strip, and clearer search-selection feedback to speed up common driver search journeys.

V3.8 adds moderation queue search, clearer moderation copy, active filter chips, and stronger result context to make admin review and driver search journeys easier.

V3.9 adds saved searches for drivers, dashboard access to rerun searches, and quicker repeat route-finding for common stop-finding journeys.

V4.0 adds driver reputation scoring, a public top-contributors leaderboard, richer dashboard contribution stats, and admin visibility into community contributors.

V4.1 adds community stats, contributor milestone progress, a public community activity strip, and stronger returning-user motivation around reputation.

V4.2 adds a recent community activity feed and surfaces fresh driver contributions across the finder, dashboard, and admin analytics.

V4.3 adds saved-search deletion, richer contributor badge styling, stronger community panel copy, and clearer admin community overview stats.

V4.4 is a map-first UI rebuild with compact top actions, a dominant mobile map, collapsible results visibility, and a cleaner browse-first flow.

V4.6 introduces a true full-screen map-first layout with map overlays, a bottom results sheet, and cleaner fixed action buttons.

V4.7 adds true app mode with full-width takeover, theme layout suppression, edge-to-edge map, and hidden site chrome for native-app style UX.

V4.8 adds true standalone app-page rendering for the shortcode page, bypassing theme headers, footers, and containers so the finder can render as a full-screen app shell.

V4.9 adds Google Maps style polish: hidden admin bar on app pages, tighter map overlays, softer cards, stronger bottom sheet styling, and refined floating actions.

V4.10 adds a final mobile polish pass with safer overlay spacing, slimmer Google Maps-style search controls, lighter map status bar styling, and improved floating action positioning.

V4.11 simplifies the top search UI into a lighter Google Maps-style layout, moves postcode/radius into the filters panel, adds a subtle auto-hiding map hint, and introduces a bottom app nav for a more native experience.

V4.12 focuses on final visual polish only: tighter top controls, clearer active location state, lighter result chrome, slimmer bottom nav, and removal of redundant floating actions.

V4.13 fixes listing action button click handling so the Details and Save buttons reliably receive taps on touch devices.

V5.0 is the first stable production baseline: persisted results-sheet preference, Google Maps directions from the detail drawer, clearer mobile actions, and final tap-target hardening.

V5.1 fixes listing-card tap handling on touch devices so the card itself, Details button, and Save button reliably open or act without tap interception.

V5.2 specifically fixes stubborn touch interception on listing cards by adding direct touch/pointer handlers to the card and Details button and removing over-aggressive pointer blocking from the card content.

V5.3 changes listing opening behavior so the detail drawer opens instantly from the search-result card data, even before the full listing API response returns. This avoids taps appearing to do nothing when the full detail request is delayed or fails.

V5.4 fixes the single listing page fatal error. The full-page listing view was calling a nearby_listings helper that was not present in the plugin build. This release adds the helper and guards the template so Open full page no longer crashes.

V5.5 switches the plugin over to WordPress users for authentication, adds logged-in ratings stored in the WordPress database, shows average rating and count on listings, and adds in-app rating controls in the detail drawer. Distance remains in miles and uses browser location when allowed.

V5.6 improves real driver usability: distance is calculated and shown consistently in miles, result cards surface ratings more clearly, navigation now opens smarter turn-by-turn map links, empty states guide the user, and the filter panel gives clearer context.

V5.7 rebuilds the single truck stop page for mobile: cleaner layout, safer field formatting, removal of raw meta text, proper quick facts/facilities/reviews sections, and a styled full-page view that matches the finder better.

V5.8 fixes the V5.7 single-page regression. The full-page template was calling helper methods that do not exist in this build. This release switches the template to the actual helper/database methods already present in the plugin and adds defensive guards so Open full page no longer throws a critical error.

V5.9 restores the site header, footer, and navigation for the finder page while keeping the map-first finder layout. This is a hybrid site mode so the finder feels part of TruckersNet instead of a fully detached standalone app.

V6.0 is a theme-integrated polish build that pulls the finder up under the site header in hybrid mode, reducing the large white gap while keeping the normal header, footer, and navigation visible.

V6.1 fixes the hybrid-mode header overlap introduced in V6.0 by removing the aggressive pull-up and letting the finder start cleanly below the site header on first load, especially on mobile.

V7.0 rolls up the next major phase into one structured release: live logged-in reviews with quick tags, ratings tied to WordPress users, saved favourites and searches, dashboard support for those user features, and logged-in submission workflows ready for admin handling. Reviews post instantly and can be audited later rather than waiting for approval.

V7.2 fixes the review interaction UI in the detail drawer. Review tags and stars now use React state instead of fragile DOM class toggles, the Post review button validates and submits from state, and the form resets correctly after posting.

V7.3 fixes the review drawer interactions on touch devices. Tag chips, stars, and the Post review button now stop bubbling out of the drawer, use touch-safe handlers, and the review post path no longer risks failing on an undefined front-end variable.

V7.4 fixes the review form defaults and interaction rules: no star is preselected anymore, a written comment is required to post, a rating must be selected before posting, and quick-tag/category selection now uses a single pointer-based toggle path so mobile taps do not misfire.

V7.5 simplifies reviews to the essentials: star rating plus written comment only. Quick categories/tags have been removed from the drawer UI and full-page display, while keeping logged-in review posting and instant publishing.

V7.6 improves review-form feedback. Users now see explicit inline validation messages when comment or rating is missing, and a clear success confirmation after posting instead of the form simply clearing with no explanation.

V7.7 hides the review form after a successful post and shows only a confirmation state instead. This keeps the drawer cleaner and avoids making users think they need to submit again.

V7.8 fixes rating display consistency: correct rating counts, proper average formatting, 'No reviews yet' state, and non-interactive summary stars.

V7.9 removes the old interactive rating buttons from the top Rating summary box in the drawer. That area is now display-only, so tapping it no longer changes the saved rating or affects the small result card.

V8.0 fixes the review count/data mismatch. Result cards now read the correct rating count field instead of the old review_count field, and the single listing page no longer overrides the live review helper with an old approved-only SQL query.

V8.1 finishes the live review cleanup. Review queries now detect both old and new schemas, treat both published and approved reviews as live for display, remove the last approved-only query from the REST listing endpoint, and replace lingering review_count uses in the app with rating_count so cards and headers stay in sync.

V8.2 completes the saved/account polish pass. Save buttons now stay in sync with the dashboard favourites list, the Saved bottom-nav entry opens directly to saved stops, Add stop now prompts sign-in when needed, and the account view includes recent submission statuses alongside saved stops and saved searches.

V9.0 is the launch-polish release. It hardens submission labels so generic 'listing' rows display a real stop name wherever possible, makes approved submission badges green and pending badges amber, improves saved/account empty states, and keeps save state language more polished across the app.

V9.1 shows review author names consistently in the app drawer and full listing page. Reviews now display the WordPress user's display name when available, with a clean 'Driver' fallback when no name is set.

V9.2 replaces the small-card text rating with a compact non-interactive five-star display plus review count, and prevents duplicate reviews by updating an existing user's review for the same stop instead of inserting another row.

V9.3 changes the small-card rating display to use the same rounded yellow star-box style as the large rating card, while keeping the small-card stars display-only.

V9.4 adds half-star support to the small-card rating display and shows the decimal average beside the stars, for example 4.5 (12), while keeping the cards display-only.

V9.5 adds discovery and engagement polish. Small result cards now show smarter listing badges: New, Trending, or Top rated based on live rating activity. Small-card review count text is clearer, and saved/account empty states stay polished.

V10.0 introduces map intelligence: rating-based pin colors, clustered markers with styled count bubbles, and a tap preview card on the map so drivers can inspect a stop before opening the full drawer.

V10.1 is the marker hotfix. It restores pins by supporting both lat/lng and latitude/longitude fields and makes marker rendering fall back cleanly before clustering is applied.

V10.2 fixes map/list sync timing. Pins now resync whenever the map becomes ready or search results change, so listings populate onto the map reliably after search, filters, and location updates. It also avoids clustering a single marker.

V10.3 adds a geocoding fallback for listings that do not yet have saved coordinates. When a stop has postcode/town data but no lat/lng, the map now tries to resolve a position through Google Maps geocoding, caches the result in local storage, and then redraws the markers.

V10.3.100 fixes radius search from the stable V10.3 base. The main search box is now geocoded into a search centre when location is not already enabled, and the REST search actually matches q against listing data fields like postcode, town/city, address, and listing name before applying the radius filter.

V10.3.100 hydrates coordinates and distance data into the list/search pipeline from the stable V10.3 base. Cards now calculate distance where possible, and radius filtering is applied using those hydrated coordinates so searched results no longer ignore distance.

V10.3.100 is a narrow location-search patch from the stable V10.3 line. It normalizes postcode search terms, broadens town/city/address matching across listing payload fields, and keeps the working map/pin behavior untouched.

V10.3.100 is a narrow stability patch from the stable V10.3.x line. It hard-removes the two-finger map hint from the app and strengthens main-search matching across listing name, town/city, postcode, and address fields while keeping the current working map/pin behavior.

V10.3.100 adds area-search mode from the stable V10.3.x line. If the main search box geocodes successfully (for example Selby or YO8 9TF), the finder treats that as an area search and returns all stops within the selected radius instead of requiring the listing text to match the typed query.

V10.3.100 fixes backend area-search filtering. When the request includes lat/lng, the REST search now treats it as an area search and skips the q text-match gate, so nearby stops are filtered by radius instead of being excluded for not matching the typed place/postcode text.

V10.3.100 strengthens area-search mode. When the main search box geocodes successfully, the app now performs a pure nearby radius search from that centre without sending q to the API. If geocoding fails, it falls back to normal text search. This prevents area searches like Selby or YO8 9TF from accidentally returning all stops or none for the wrong reason.

V10.3.100 improves geocode accuracy for area searches. The search geocoder now restricts to GB and scores returned results so postcode and locality matches are preferred over broad region/country matches before radius filtering runs.

V10.3.10 fixes area-search distance filtering by adding the missing frontend haversine miles calculation used by hydrateListDistances(). Searches like Selby and YO8 9TF can now calculate distance_miles correctly before the 25-mile filter is applied.


V10.7.1 adds a GitHub updater scaffold so future releases can surface as WordPress plugin updates once your GitHub repo details are configured.
