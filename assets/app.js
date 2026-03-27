(function () {
  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement("script");
      s.src = src;
      s.async = true;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function ensureReact() {
    if (window.React && window.ReactDOM) return Promise.resolve();
    return Promise.all([
      loadScript("https://unpkg.com/react@18/umd/react.production.min.js"),
      loadScript("https://unpkg.com/react-dom@18/umd/react-dom.production.min.js")
    ]);
  }

  function ensureMaps() {
    if (!window.TSF_CONFIG || !window.TSF_CONFIG.googleMapsKey) return Promise.resolve();
    var tasks = [];
    if (!(window.google && window.google.maps)) {
      tasks.push(loadScript("https://maps.googleapis.com/maps/api/js?key=" + encodeURIComponent(window.TSF_CONFIG.googleMapsKey)));
    }
    if (!window.markerClusterer || !window.markerClusterer.MarkerClusterer) {
      tasks.push(loadScript("https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"));
    }
    return Promise.all(tasks).catch(function(){ return Promise.resolve(); });
  }

  function h() { return React.createElement.apply(null, arguments); }

  Promise.resolve().then(ensureReact).then(ensureMaps).then(function () {

    function apiFetch(path, options) {
      return fetch((window.TSF_CONFIG ? window.TSF_CONFIG.apiBase : "") + path, options || {})
        .then(async function (r) {
          var data = null;
          try { data = await r.json(); } catch (e) { data = null; }
          return { ok: r.ok, status: r.status, data: data };
        });
    }

    function App() {
      var defaultFilters = { showers:false, secure:false, overnight:false, fuel:false, food:false, featured:false };

      var tokenState = React.useState(localStorage.getItem("tsf_token") || "");
      var token = tokenState[0], setToken = tokenState[1];
      var meState = React.useState(null);
      var me = meState[0], setMe = meState[1];
      var queryState = React.useState("");
      var query = queryState[0], setQuery = queryState[1];
      var postcodeState = React.useState("");
      var postcode = postcodeState[0], setPostcode = postcodeState[1];
      var radiusState = React.useState("25");
      var radius = radiusState[0], setRadius = radiusState[1];
      var filtersState = React.useState(defaultFilters);
      var filters = filtersState[0], setFilters = filtersState[1];
      var sortState = React.useState("distance");
      var sortBy = sortState[0], setSortBy = sortState[1];
      var itemsState = React.useState([]);
      var items = itemsState[0], setItems = itemsState[1];
      var selectedState = React.useState(null);
      var selected = selectedState[0], setSelected = selectedState[1];
      var detailState = React.useState(null);
      var detail = detailState[0], setDetail = detailState[1];
      var reviewTextState = React.useState("");
      var reviewText = reviewTextState[0], setReviewText = reviewTextState[1];
      var reviewRatingState = React.useState(0);
      var reviewRating = reviewRatingState[0], setReviewRating = reviewRatingState[1];
      var reviewErrorState = React.useState("");
      var reviewError = reviewErrorState[0], setReviewError = reviewErrorState[1];
      var reviewSuccessState = React.useState("");
      var reviewSuccess = reviewSuccessState[0], setReviewSuccess = reviewSuccessState[1];
      var reviewSubmittingState = React.useState(false);
      var reviewSubmitting = reviewSubmittingState[0], setReviewSubmitting = reviewSubmittingState[1];
      var messageState = React.useState("");
      var message = messageState[0], setMessage = messageState[1];
      var loadingState = React.useState(false);
      var loading = loadingState[0], setLoading = loadingState[1];
      var locatingState = React.useState(false);
      var locating = locatingState[0], setLocating = locatingState[1];
      var geoState = React.useState({ enabled:false, lat:null, lng:null });
      var geo = geoState[0], setGeo = geoState[1];
      var showFiltersState = React.useState(false);
      var showFilters = showFiltersState[0], setShowFilters = showFiltersState[1];
      var initialSheetOpen = localStorage.getItem("tsf_sheet_open");
      var showSheetState = React.useState(initialSheetOpen === null ? true : initialSheetOpen === "1");
      var showSheet = showSheetState[0], setShowSheet = showSheetState[1];
      var showAccountState = React.useState(false);
      var showAccount = showAccountState[0], setShowAccount = showAccountState[1];
      var showSubmitState = React.useState(false);
      var showSubmit = showSubmitState[0], setShowSubmit = showSubmitState[1];
      var accountTabState = React.useState("login");
      var accountTab = accountTabState[0], setAccountTab = accountTabState[1];
      var accountViewState = React.useState("overview");
      var accountView = accountViewState[0], setAccountView = accountViewState[1];
      var dashboardState = React.useState({ submissions:[], reviews:[], favourites:[], saved_searches:[], reputation:null, progress:null });
      var dashboard = dashboardState[0], setDashboard = dashboardState[1];
      var communityState = React.useState({ listings:0, reviews:0, photos:0, contributors:0 });
      var communityStats = communityState[0], setCommunityStats = communityState[1];
      var duplicateState = React.useState(null);
      var duplicateBlock = duplicateState[0], setDuplicateBlock = duplicateState[1];
      var mapHintState = React.useState(false);
      var showMapHint = false, setShowMapHint = function(){};
      var mapPreviewState = React.useState(null);
      var mapPreview = mapPreviewState[0], setMapPreview = mapPreviewState[1];
      var mapReadyState = React.useState(false);
      var mapReady = mapReadyState[0], setMapReady = mapReadyState[1];
      var mapMovedState = React.useState(false);
      var mapMoved = mapMovedState[0], setMapMoved = mapMovedState[1];
      var mapCenterState = React.useState(null);
      var mapCenter = mapCenterState[0], setMapCenter = mapCenterState[1];
      var geocodeQueueRef = React.useRef({});
      var geocoderRef = React.useRef(null);

      var mapEl = React.useRef(null);
      var mapRef = React.useRef(null);
      var markersRef = React.useRef([]);
      var clusterRef = React.useRef(null);

      function sortListings(list) {
        var arr = (list || []).slice();
        if (sortBy === "rating") {
          arr.sort(function(a,b){ if ((b.rating||0)!==(a.rating||0)) return (b.rating||0)-(a.rating||0); return (b.rating_count||0)-(a.rating_count||0); });
          return arr;
        }
        if (sortBy === "reviews") {
          arr.sort(function(a,b){ if ((b.rating_count||0)!==(a.rating_count||0)) return (b.rating_count||0)-(a.rating_count||0); return (b.rating||0)-(a.rating||0); });
          return arr;
        }
        if (sortBy === "distance") {
          arr.sort(function(a,b){ var ad = typeof a.distance_miles === "number" ? a.distance_miles : 99999; var bd = typeof b.distance_miles === "number" ? b.distance_miles : 99999; return ad - bd; });
          return arr;
        }
        arr.sort(function(a,b){ if ((b.featured||0)!==(a.featured||0)) return (b.featured||0)-(a.featured||0); if ((b.rating||0)!==(a.rating||0)) return (b.rating||0)-(a.rating||0); return (b.rating_count||0)-(a.rating_count||0); });
        return arr;
      }

      function getListingBadge(item) {
        var rating = Number((item && item.rating) || 0);
        var count = Number((item && item.rating_count) || 0);
        if (count >= 3 && rating >= 4.5) {
          return { label: "Top rated", className: "tsf-badge-top-rated" };
        }
        if (count >= 2) {
          return { label: "Trending", className: "tsf-badge-trending" };
        }
        return { label: "New", className: "tsf-badge-new" };
      }

function getMarkerStrokeColor(item, selected) {
  try {
    if (selected && item && selected && Number(item.id) === Number(selected.id)) return "#0b1538";
  } catch (e) {}
  return "#ffffff";
}

function getMarkerScale(item, selected) {
  try {
    if (selected && item && Number(item.id) === Number(selected.id)) return 1.18;
  } catch (e) {}
  return 1;
}

      function formatMiles(miles) {
        miles = Number(miles);
        if (!isFinite(miles) || miles <= 0) return "Distance not set";
        if (miles < 1) return "<1 mi";
        return Math.round(miles) + " mi";
      }

      function renderStarBoxes(rating) {
        var value = parseFloat(rating);
        if (isNaN(value)) value = 0;
        value = Math.max(0, Math.min(5, value));
        return h("span", { className:"tsf-stars-box", "aria-hidden":"true" },
          [1,2,3,4,5].map(function(i){
            var level = "is-empty";
            if (value >= i) {
              level = "is-full";
            } else if (value >= i - 0.5) {
              level = "is-half";
            }
            return h("span", { className:"tsf-star-box " + level }, "★");
          })
        );
      }

      function renderSmallStars(rating) {
        var rounded = Math.max(0, Math.min(5, Math.round(Number(rating || 0))));
        return h("span", { className:"tsf-stars-small", "aria-hidden":"true" },
          [1,2,3,4,5].map(function(i){
            return h("span", { className: i <= rounded ? "tsf-stars-small-star is-active" : "tsf-stars-small-star" }, "★");
          })
        );
      }

      function isSavedStop(postId) {
        return !!((dashboard.favourites || []).find(function(f){ return Number(f.id) === Number(postId); }));
      }

      function coordsForItem(item) {
        if (!item) return null;
        var lat = item.lat;
        var lng = item.lng;

        if (lat === undefined || lat === null || lat === "") lat = item.latitude;
        if (lng === undefined || lng === null || lng === "") lng = item.longitude;

        lat = parseFloat(lat);
        lng = parseFloat(lng);

        if (!isFinite(lat) || !isFinite(lng)) return null;
        return { lat: lat, lng: lng };
      }

      function geocodeKeyForItem(item) {
        return String((item && item.id) || "") + "|" + String((item && item.postcode) || "") + "|" + String((item && item.town_city) || "");
      }

      function geocodeAddressForItem(item) {
        if (!item) return "";
        var parts = [];
        if (item.title) parts.push(item.title);
        if (item.postcode) parts.push(item.postcode);
        if (item.town_city) parts.push(item.town_city);
        return parts.filter(Boolean).join(", ");
      }

      function applyResolvedCoords(item, coords) {
        if (!item || !coords) return;
        item.lat = coords.lat;
        item.lng = coords.lng;
        setItems(function(curr){
          return (curr || []).map(function(x){
            if (Number(x.id) !== Number(item.id)) return x;
            return Object.assign({}, x, { lat: coords.lat, lng: coords.lng });
          });
        });
        setDetail(function(curr){
          if (!curr || !curr.listing || Number(curr.listing.id) !== Number(item.id)) return curr;
          return Object.assign({}, curr, { listing: Object.assign({}, curr.listing, { lat: coords.lat, lng: coords.lng }) });
        });
      }

      function resolveCoordsForItem(item) {
        if (!item || !(window.google && window.google.maps)) return Promise.resolve(null);
        var existing = coordsForItem(item);
        if (existing) return Promise.resolve(existing);

        var cacheKey = geocodeKeyForItem(item);
        if (!cacheKey) return Promise.resolve(null);

        try {
          var raw = window.localStorage ? window.localStorage.getItem("tsf_geo_" + cacheKey) : null;
          if (raw) {
            var parsed = JSON.parse(raw);
            if (parsed && isFinite(parsed.lat) && isFinite(parsed.lng)) {
              applyResolvedCoords(item, parsed);
              return Promise.resolve(parsed);
            }
          }
        } catch (e) {}

        if (geocodeQueueRef.current[cacheKey]) {
          return geocodeQueueRef.current[cacheKey];
        }

        var address = geocodeAddressForItem(item);
        if (!address) return Promise.resolve(null);

        if (!geocoderRef.current) geocoderRef.current = new google.maps.Geocoder();

        geocodeQueueRef.current[cacheKey] = new Promise(function(resolve){
          geocoderRef.current.geocode({ address: address, region: "uk" }, function(results, status){
            var coords = null;
            if (status === "OK" && results && results[0] && results[0].geometry && results[0].geometry.location) {
              coords = {
                lat: results[0].geometry.location.lat(),
                lng: results[0].geometry.location.lng()
              };
              try {
                if (window.localStorage) window.localStorage.setItem("tsf_geo_" + cacheKey, JSON.stringify(coords));
              } catch (e) {}
              applyResolvedCoords(item, coords);
            }
            delete geocodeQueueRef.current[cacheKey];
            resolve(coords);
          });
        });

        return geocodeQueueRef.current[cacheKey];
      }

      function markerColorFor(item) {
        var count = Number((item && item.rating_count) || 0);
        var rating = Number((item && item.rating) || 0);
        if (count <= 0) return "#94a3b8";
        if (rating >= 4.5) return "#16a34a";
        if (rating >= 3) return "#eab308";
        return "#dc2626";
      }

      function markerIconFor(item) {
        return {
          path: google.maps.SymbolPath.CIRCLE,
          fillColor: markerColorFor(item),
          fillOpacity: 1,
          strokeColor: "#ffffff",
          strokeWeight: 2,
          scale: 9
        };
      }

      function clusterColorFor(markers) {
        var total = 0;
        var count = 0;
        (markers || []).forEach(function(m){
          if (m && m._tsfItem) {
            total += Number(m._tsfItem.rating || 0);
            count += 1;
          }
        });
        var avg = count ? (total / count) : 0;
        if (!count) return "#94a3b8";
        if (avg >= 4.5) return "#16a34a";
        if (avg >= 3) return "#eab308";
        return "#dc2626";
      }

      function openMapPreview(item) {
        setMapPreview(item);
      }

      function clearMarkers() {
        setMapPreview(null);
        if (clusterRef.current && clusterRef.current.clearMarkers) {
          clusterRef.current.clearMarkers();
          clusterRef.current = null;
        }
        markersRef.current.forEach(function(m){ if (m && m.setMap) m.setMap(null); });
        markersRef.current = [];
      }

      function syncMap(list) {
        if (!(window.google && window.google.maps) || !mapRef.current) return;
        clearMarkers();
        var bounds = new google.maps.LatLngBounds();
        var built = [];
        var hasAny = false;
        var pending = [];

        (list || []).forEach(function(item) {
          var pos = coordsForItem(item);
          if (pos) {
            var marker = new google.maps.Marker({
              position: pos,
              title: item.title,
              icon: markerIconFor(item),
              map: mapRef.current
            });

            marker._tsfItem = item;
            marker.addListener("click", function () {
              openMapPreview(item);
              if (mapRef.current) mapRef.current.panTo(pos);
            });

            built.push(marker);
            bounds.extend(pos);
            hasAny = true;
          } else {
            pending.push(item);
          }
        });

        markersRef.current = built;

        if (built.length > 1 && window.markerClusterer && window.markerClusterer.MarkerClusterer) {
          built.forEach(function(m){ m.setMap(null); });
          clusterRef.current = new window.markerClusterer.MarkerClusterer({
            map: mapRef.current,
            markers: built,
            renderer: {
              render: function(clusterArgs) {
                var color = clusterColorFor(clusterArgs.markers || []);
                return new google.maps.Marker({
                  position: clusterArgs.position,
                  label: {
                    text: String(clusterArgs.count),
                    color: "#ffffff",
                    fontSize: "13px",
                    fontWeight: "700"
                  },
                  icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: color,
                    fillOpacity: 0.95,
                    strokeColor: "#ffffff",
                    strokeWeight: 2,
                    scale: Math.max(18, Math.min(28, 14 + (clusterArgs.count / 3)))
                  }
                });
              }
            }
          });
        }

        if (hasAny) {
          mapRef.current.fitBounds(bounds);
          if (built.length === 1) mapRef.current.setZoom(12);
        }

        if (pending.length) {
          Promise.all(pending.slice(0, 20).map(resolveCoordsForItem)).then(function(results){
            var resolvedAny = (results || []).some(function(x){ return !!x; });
            if (resolvedAny) {
              syncMap(items || list || []);
            }
          });
        }
      }

      React.useEffect(function(){
        if (mapReady) syncMap(items || []);
      }, [mapReady, items]);

      function haversineMiles(lat1, lng1, lat2, lng2) {
        lat1 = Number(lat1);
        lng1 = Number(lng1);
        lat2 = Number(lat2);
        lng2 = Number(lng2);

        if (![lat1, lng1, lat2, lng2].every(function(v){ return isFinite(v); })) return NaN;

        var toRad = function(v){ return v * Math.PI / 180; };
        var dLat = toRad(lat2 - lat1);
        var dLng = toRad(lng2 - lng1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
          Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
          Math.sin(dLng / 2) * Math.sin(dLng / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return 3958.8 * c;
      }

      function hydrateListDistances(list, center) {
        list = Array.isArray(list) ? list.slice() : [];
        if (!list.length) return Promise.resolve(list);

        return Promise.all(list.map(function(item){
          var direct = coordsForItem(item);
          if (direct) {
            var next = Object.assign({}, item, { lat: direct.lat, lng: direct.lng });
            if (center && isFinite(center.lat) && isFinite(center.lng)) {
              var miles = haversineMiles(center.lat, center.lng, direct.lat, direct.lng);
              if (isFinite(miles)) next.distance_miles = miles;
            }
            return Promise.resolve(next);
          }

          return resolveCoordsForItem(item).then(function(found){
            if (!found) return item;
            var next = Object.assign({}, item, { lat: found.lat, lng: found.lng });
            if (center && isFinite(center.lat) && isFinite(center.lng)) {
              var miles = haversineMiles(center.lat, center.lng, found.lat, found.lng);
              if (isFinite(miles)) next.distance_miles = miles;
            }
            return next;
          }).catch(function(){
            return item;
          });
        }));
      }

      function normalizeSearchQuery(queryText) {
        var q = String(queryText || "").trim();
        // normalize UK-style postcode without affecting normal place-name searches
        var compact = q.replace(/\s+/g, "").toUpperCase();
        if (/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/.test(compact)) {
          return compact.slice(0, compact.length - 3) + " " + compact.slice(-3);
        }
        return q;
      }

      function geocodeSearchQuery(queryText) {
        return new Promise(function(resolve){
          if (!(window.google && window.google.maps && queryText)) { resolve(null); return; }
          try {
            var geocoder = new google.maps.Geocoder();
            geocoder.geocode({
              address: normalizeSearchQuery(queryText),
              region: "uk",
              componentRestrictions: { country: "GB" }
            }, function(results, status){
              if (status !== "OK" || !results || !results.length) {
                resolve(null);
                return;
              }

              function scoreResult(r) {
                var score = 0;
                var types = Array.isArray(r.types) ? r.types : [];
                if (types.indexOf("postal_code") !== -1) score += 100;
                if (types.indexOf("postal_town") !== -1) score += 80;
                if (types.indexOf("locality") !== -1) score += 70;
                if (types.indexOf("administrative_area_level_2") !== -1) score += 30;
                if (types.indexOf("country") !== -1) score -= 100;

                var comps = Array.isArray(r.address_components) ? r.address_components : [];
                var hasGB = comps.some(function(c){
                  return Array.isArray(c.types) && c.types.indexOf("country") !== -1 && ((c.short_name || "").toUpperCase() === "GB" || (c.long_name || "").toUpperCase() === "UNITED KINGDOM");
                });
                if (hasGB) score += 20;

                var formatted = String(r.formatted_address || "").toUpperCase();
                var qNorm = String(normalizeSearchQuery(queryText) || "").toUpperCase();
                var compactQ = qNorm.replace(/\s+/g, "");
                var compactFormatted = formatted.replace(/\s+/g, "");
                if (qNorm && formatted.indexOf(qNorm) !== -1) score += 40;
                if (compactQ && compactFormatted.indexOf(compactQ) !== -1) score += 40;

                return score;
              }

              var best = results.slice().sort(function(a, b){
                return scoreResult(b) - scoreResult(a);
              })[0];

              if (!best || !best.geometry || !best.geometry.location) {
                resolve(null);
                return;
              }

              resolve({
                lat: best.geometry.location.lat(),
                lng: best.geometry.location.lng()
              });
            });
          } catch (e) {
            resolve(null);
          }
        });
      }

      function runSearch() {
        setLoading(true);
        setMessage("");

        var normalizedQuery = (typeof normalizeSearchQuery === "function") ? normalizeSearchQuery(query) : String(query || "").trim();

        var applySearch = function(searchCoords, areaMode){
          var params = new URLSearchParams();

          // In area mode, do NOT send q to the API.
          // We want a pure nearby search from the geocoded centre.
          if (!areaMode && normalizedQuery) params.set("q", normalizedQuery);
          if (postcode) params.set("postcode", postcode);
          if (radius) params.set("radius", radius);

          if (areaMode && searchCoords && searchCoords.lat !== null && searchCoords.lng !== null) {
            params.set("lat", searchCoords.lat);
            params.set("lng", searchCoords.lng);
          } else if (geo.enabled && geo.lat !== null && geo.lng !== null) {
            params.set("lat", geo.lat);
            params.set("lng", geo.lng);
          }

          if (filters.showers) params.set("showers", "1");
          if (filters.secure) params.set("secure", "1");
          if (filters.overnight) params.set("overnight", "1");
          if (filters.fuel) params.set("fuel", "1");
          if (filters.food) params.set("food", "1");
          if (filters.featured) params.set("featured", "1");

          apiFetch("/search?" + params.toString()).then(function(res){
            var list = Array.isArray(res.data) ? sortListings(res.data) : [];
            var activeCenter = null;

            if (areaMode && searchCoords && searchCoords.lat !== null && searchCoords.lng !== null) {
              activeCenter = { lat: Number(searchCoords.lat), lng: Number(searchCoords.lng) };
            } else if (geo.enabled && geo.lat !== null && geo.lng !== null) {
              activeCenter = { lat: Number(geo.lat), lng: Number(geo.lng) };
            }

            hydrateListDistances(list, activeCenter).then(function(hydrated){
              hydrated = Array.isArray(hydrated) ? hydrated : list;

              if (areaMode && activeCenter) {
                hydrated = hydrated.filter(function(item){
                  return typeof item.distance_miles === "number" && isFinite(item.distance_miles) &&
                    item.distance_miles <= Number(radius || 25);
                }).sort(function(a,b){
                  return Number(a.distance_miles || 0) - Number(b.distance_miles || 0);
                });
              }

              setItems(hydrated);
              if (hydrated.length) {
                setSelected(hydrated[0]);
                setShowSheet(true);
                setMessage("");
              } else {
                setSelected(null);
                setMessage((radius ? "No stops within " + radius + " miles. Try increasing the radius or changing the area." : "No stops found for this search yet."));
              }
              syncMap(hydrated);
          if (typeof setMapMoved === "function") setMapMoved(false);
            if (typeof setMapMoved === "function") setMapMoved(false);
          if (typeof setMapMoved === "function") setMapMoved(false);
            if (typeof setMapMoved === "function") setMapMoved(false);
            });
          }).catch(function(){
            setMessage("Search failed.");
          }).finally(function(){ setLoading(false); });
        };

        if (normalizedQuery) {
          geocodeSearchQuery(normalizedQuery).then(function(coords){
            if (coords && coords.lat !== null && coords.lng !== null) {
              applySearch(coords, true);
            } else {
              applySearch(null, false);
            }
          });
          return;
        }

        applySearch(null, false);
      }

      function requestLocation() {
        if (!navigator.geolocation) { setMessage("Location is not available on this device."); return; }
        setLocating(true);
        navigator.geolocation.getCurrentPosition(function(pos){
          setGeo({ enabled:true, lat: pos.coords.latitude, lng: pos.coords.longitude });
          setLocating(false);
          setSortBy("distance");
          setTimeout(runSearch, 50);
        }, function(){
          setLocating(false);
          setMessage("Could not get your location.");
        }, { enableHighAccuracy:true, timeout:10000, maximumAge:60000 });
      }

      function directionsUrlFor(listing) {
        if (!listing) return "";
        var isiPhone = /iPad|iPhone|iPod/.test(navigator.userAgent || "");
        var isAndroid = /Android/.test(navigator.userAgent || "");
        var dest = "";
        if (listing.lat && listing.lng) {
          dest = String(listing.lat) + "," + String(listing.lng);
        } else {
          var parts = [];
          if (listing.title) parts.push(listing.title);
          if (listing.postcode) parts.push(listing.postcode);
          if (listing.town_city) parts.push(listing.town_city);
          dest = parts.join(", ");
        }
        if (!dest) return "";

        if (isiPhone) {
          return "https://maps.apple.com/?daddr=" + encodeURIComponent(dest);
        }
        if (isAndroid) {
          return "https://www.google.com/maps/dir/?api=1&destination=" + encodeURIComponent(dest);
        }
        return "https://www.google.com/maps/dir/?api=1&destination=" + encodeURIComponent(dest);
      }

      function openDetailTap(item, e) {
        if (e && e.preventDefault) e.preventDefault();
        if (e && e.stopPropagation) e.stopPropagation();
        openDetail(item);
      }

      function openDetail(item) {
        setSelected(item);
        setShowSheet(true);
        setReviewText("");
        setReviewRating(0);
        setReviewError("");
        setReviewSuccess("");
        setReviewSubmitting(false);
        setDetail({
          listing: item,
          details: {},
          reviews: [],
          photos: [],
          nearby: [],
          content: ""
        });
        apiFetch("/listing/" + item.id).then(function(res){
          if (res && res.ok && res.data) {
            setDetail(res.data);
          } else {
            setMessage("Loaded basic listing view. Full details are not available yet.");
          }
        }).catch(function(){
          setMessage("Loaded basic listing view. Full details are not available yet.");
        });
        if (mapRef.current && item.lat && item.lng) {
          mapRef.current.panTo({ lat: parseFloat(item.lat), lng: parseFloat(item.lng) });
          mapRef.current.setZoom(12);
        }
      }

      function loadMe() {
        if (!token) { setMe(null); return; }
        apiFetch("/me", { headers: { Authorization: "Bearer " + token } }).then(function(res){
          if (res.ok) setMe(res.data);
          else { setMe(null); localStorage.removeItem("tsf_token"); setToken(""); }
        });
      }

      function loadDashboard() {
        if (!token) { setShowAccount(true); return; }
        apiFetch("/my-dashboard", { headers: { Authorization: "Bearer " + token } }).then(function(res){
          if (res && res.ok && res.data) {
            setDashboard(res.data);
            setShowAccount(true);
          }
        });
      }

      function authSubmit(e, mode) {
        e.preventDefault();
        var fd = new FormData(e.target);
        var payload = { email: fd.get("email"), password: fd.get("password"), display_name: fd.get("display_name") || "" };
        apiFetch(mode === "register" ? "/register" : "/login", {
          method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload)
        }).then(function(res){
          if (res.ok && res.data && res.data.token) {
            localStorage.setItem("tsf_token", res.data.token);
            setToken(res.data.token);
            setShowAccount(false);
            setMessage("Signed in.");
            setTimeout(function(){ loadDashboard(false); }, 50);
          } else if (res.ok && mode === "register") {
            setAccountTab("login");
            setMessage("Account created. Please log in.");
          } else {
            setMessage((res.data && res.data.message) || "Could not sign in.");
          }
        });
      }

      function logout() {
        localStorage.removeItem("tsf_token");
        setToken("");
        setMe(null);
        setDashboard({ submissions:[], reviews:[], favourites:[], saved_searches:[], reputation:null, progress:null });
        setShowAccount(false);
      }

      function toggleSave(postId) {
        if (!token) { setShowAccount(true); return; }
        apiFetch("/favourite", {
          method: "POST",
          headers: { "Content-Type": "application/json", Authorization: "Bearer " + token },
          body: JSON.stringify({ post_id: postId })
        }).then(function(res){
          if (res && res.ok && res.data) {
            setMessage(res.data.saved ? "Saved to your account." : "Removed from saved.");
            loadDashboard();
          }
        });
      }

      function submitRating(postId, ratingValue) {
        if (!token) { setShowAccount(true); return; }
        apiFetch("/rate", {
          method:"POST",
          headers:{ "Content-Type":"application/json", Authorization:"Bearer " + token },
          body: JSON.stringify({ post_id: postId, rating: ratingValue })
        }).then(function(res){
          if (res && res.ok && res.data) {
            setDetail(function(curr){
              if (!curr) return curr;
              var nextListing = Object.assign({}, curr.listing, {
                rating: res.data.rating,
                rating_count: res.data.rating_count
              });
              return Object.assign({}, curr, {
                listing: nextListing,
                user_rating: res.data.user_rating
              });
            });
            setItems(function(curr){
              return (curr || []).map(function(item){
                if (item.id !== postId) return item;
                return Object.assign({}, item, {
                  rating: res.data.rating,
                  rating_count: res.data.rating_count
                });
              });
            });
            setMessage("Rating saved.");
          } else {
            setMessage("Could not save rating.");
          }
        }).catch(function(){
          setMessage("Could not save rating.");
        });
      }

      
function submitReview(postId, ratingValue, reviewText) {
        if (!token) { setShowAccount(true); return; }
        setReviewError("");
        setReviewSuccess("");
        setReviewSubmitting(true);
        apiFetch("/submit-review", {
          method:"POST",
          headers:{ "Content-Type":"application/json", Authorization:"Bearer " + token },
          body: JSON.stringify({ post_id: postId, rating: ratingValue, review_text: reviewText })
        }).then(function(res){
          if (res && res.ok && res.data) {
            setDetail(function(curr){
              if (!curr) return curr;
              var nextReviews = [{
                rating: ratingValue,
                review_text: reviewText,
                author_name: (typeof me !== "undefined" && me && me.display_name) ? me.display_name : "Driver"
              }].concat(curr.reviews || []);
              var nextListing = Object.assign({}, curr.listing, {
                rating: res.data.rating || curr.listing.rating || ratingValue,
                rating_count: (curr.listing.rating_count || 0) + 1
              });
              return Object.assign({}, curr, {
                listing: nextListing,
                reviews: nextReviews
              });
            });
            setItems(function(curr){
              return (curr || []).map(function(item){
                if (item.id !== postId) return item;
                return Object.assign({}, item, {
                  rating: res.data.rating || item.rating || ratingValue,
                  rating_count: (item.rating_count || 0) + 1
                });
              });
            });
            setReviewText("");
            setReviewRating(0);
            setReviewSuccess("Your review has been saved.");
            setMessage("Your review has been saved.");
          } else {
            setReviewError((res && res.data && res.data.message) ? res.data.message : "Could not post review.");
            setMessage((res && res.data && res.data.message) ? res.data.message : "Could not post review.");
          }
        }).catch(function(){
          setReviewError("Could not post review.");
          setMessage("Could not post review.");
        }).finally(function(){
          setReviewSubmitting(false);
        });
      }

      function saveSearch() {
        if (!token) { setShowAccount(true); return; }
        var label = window.prompt("Name this search");
        if (!label) return;
        apiFetch("/save-search", {
          method:"POST",
          headers:{ "Content-Type":"application/json", Authorization:"Bearer " + token },
          body: JSON.stringify({ label: label, search_payload: { query: query, postcode: postcode, radius: radius, filters: filters, sortBy: sortBy } })
        }).then(function(res){
          if (res.ok) setMessage("Search saved.");
        });
      }

      function deleteSavedSearch(id) {
        apiFetch("/delete-saved-search", {
          method:"POST",
          headers:{ "Content-Type":"application/json", Authorization:"Bearer " + token },
          body: JSON.stringify({ id:id })
        }).then(function(){ loadDashboard(); });
      }

      function applySavedSearch(raw) {
        var payload = raw;
        if (typeof raw === "string") { try { payload = JSON.parse(raw); } catch (e) { payload = {}; } }
        payload = payload || {};
        setQuery(payload.query || "");
        setPostcode(payload.postcode || "");
        setRadius(payload.radius || "25");
        setFilters(payload.filters || defaultFilters);
        setSortBy(payload.sortBy || "distance");
        setShowAccount(false);
        setTimeout(runSearch, 80);
      }

      function submitListing(e) {
        e.preventDefault();
        if (!token) { setShowAccount(true); return; }
        var fd = new FormData(e.target);
        var payload = {
          title: fd.get("title"), description: fd.get("description"), town_city: fd.get("town_city"), postcode: fd.get("postcode"),
          parking_type: fd.get("parking_type"), opening_hours: fd.get("opening_hours"), price_night: fd.get("price_night"),
          showers: fd.get("showers") ? 1 : 0, secure_parking: fd.get("secure_parking") ? 1 : 0, overnight_parking: fd.get("overnight_parking") ? 1 : 0,
          fuel: fd.get("fuel") ? 1 : 0, food: fd.get("food") ? 1 : 0, toilets: fd.get("toilets") ? 1 : 0
        };
        apiFetch("/submit-listing", {
          method:"POST", headers:{ "Content-Type":"application/json", Authorization:"Bearer " + token }, body: JSON.stringify(payload)
        }).then(function(res){
          if (res.ok && res.data && res.data.ok) {
            setMessage("Stop submitted for review.");
            setShowSubmit(false);
            setDuplicateBlock(null);
            e.target.reset();
            loadDashboard(false, "submissions");
          } else if (res.data && res.data.code === "duplicate") {
            var d = res.data.data || {};
            setDuplicateBlock({ title:d.duplicate_title || "Existing listing", url:d.duplicate_url || "" });
            setMessage("This stop already exists.");
          }
        });
      }

      React.useEffect(function(){ loadMe(); }, [token]);

      React.useEffect(function(){
        try { localStorage.setItem("tsf_sheet_open", showSheet ? "1" : "0"); } catch (e) {}
      }, [showSheet]);

      React.useEffect(function(){
        if (mapEl.current && window.google && window.google.maps && !mapRef.current) {
          mapRef.current = new google.maps.Map(mapEl.current, {
            center: { lat: 53.5, lng: -1.5 }, zoom: 6, mapTypeControl: false, streetViewControl: false, fullscreenControl: false, gestureHandling: "greedy"
          });
          geocoderRef.current = new google.maps.Geocoder();
          mapRef.current.addListener("click", function(){ setMapPreview(null); });
          mapRef.current.addListener("idle", function(){
            try {
              var c = mapRef.current && mapRef.current.getCenter ? mapRef.current.getCenter() : null;
              if (c) setMapCenter({ lat: c.lat(), lng: c.lng() });
              setMapMoved(true);
            } catch (e) {}
          });
          setMapReady(true);
        }
      }, [mapEl.current]);

      React.useEffect(function(){
        apiFetch("/community-stats").then(function(res){ if (res.ok && res.data) setCommunityStats(res.data); });
        runSearch();
        setTimeout(requestLocation, 600);
      }, []);

      var accountModal = showAccount ? h("div", { className:"tsf-modal-backdrop", onClick:function(){ setShowAccount(false); } },
        h("div", { className:"tsf-modal", onClick:function(e){ e.stopPropagation(); } },
          token && me ? h("div", null,
            h("div", { className:"tsf-modal-head" }, h("h3", null, "Your account"), h("button", { className:"tsf-close", onClick:function(){ setShowAccount(false); } }, "×")),
            dashboard.reputation ? h("div", { className:"tsf-stats-grid" },
              h("div", { className:"tsf-stat" }, h("strong", null, dashboard.reputation.label || "New"), h("span", null, "Level")),
              h("div", { className:"tsf-stat" }, h("strong", null, dashboard.reputation.score || 0), h("span", null, "Score")),
              h("div", { className:"tsf-stat" }, h("strong", null, (dashboard.favourites || []).length), h("span", null, "Saved"))
            ) : null,
            h("div", { className:"tsf-segmented" },
              h("button", { className: accountView === "overview" ? "is-active" : "", onClick:function(){ setAccountView("overview"); } }, "Overview"),
              h("button", { className: accountView === "saved" ? "is-active" : "", onClick:function(){ setAccountView("saved"); } }, "Saved"),
              h("button", { className: accountView === "submissions" ? "is-active" : "", onClick:function(){ setAccountView("submissions"); } }, "Submissions")
            ),
            accountView === "saved" ? h("div", null,
              h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Saved stops (" + ((dashboard.favourites || []).length) + ")"),
                (dashboard.favourites || []).length ? dashboard.favourites.map(function(f){ return h("div", { className:"tsf-mini-row", key:"fav-"+f.id }, h("button", { className:"tsf-link", onClick:function(){ setShowAccount(false); openDetail(f); } }, f.title), h("button", { className:"tsf-link tsf-link-danger", onClick:function(){ toggleSave(f.id); } }, "Remove")); })
                : h("div", { className:"tsf-card-meta" }, "No saved stops yet — tap Save on any stop to keep it.")
              ),
              h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Saved searches (" + ((dashboard.saved_searches || []).length) + ")"),
                (dashboard.saved_searches || []).length ? dashboard.saved_searches.map(function(s){ return h("div", { className:"tsf-mini-row", key:"ss-"+s.id }, h("button", { className:"tsf-link", onClick:function(){ applySavedSearch(s.search_payload); } }, s.label), h("button", { className:"tsf-link tsf-link-danger", onClick:function(){ deleteSavedSearch(s.id); } }, "Delete")); })
                : h("div", { className:"tsf-card-meta" }, "No saved searches yet — save a search to revisit it later.")
              )
            ) : accountView === "submissions" ? h("div", null,
              h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Recent submissions"),
                (dashboard.submissions || []).length ? dashboard.submissions.map(function(s){ return h("div", { className:"tsf-mini-row", key:"sub-"+s.id }, h("button", { className:"tsf-link", disabled: !(s.post_url || s.post_id), onClick:function(){ if (s.post_url) { window.location.href = s.post_url; } else if (s.post_id) { setShowAccount(false); openDetail({ id: s.post_id, title: s.name || "Submission" }); } } }, (((s.name && String(s.name).toLowerCase() !== "listing") ? s.name : "") || ((s.title && String(s.title).toLowerCase() !== "listing") ? s.title : "") || "New stop") + (s.town || s.postcode ? (" — " + [s.town, s.postcode].filter(Boolean).join(" • ")) : "")), h("span", { className:"tsf-status-pill tsf-status-" + String(s.status || "pending").toLowerCase() }, s.status || "pending")); })
                : h("div", { className:"tsf-card-meta" }, "No submissions yet — add a stop to grow the map.")
              ),
              h("div", { className:"tsf-card-meta" }, "New stops are submitted as pending and reviewed in admin before they go live.")
            ) : h("div", null,
              h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Saved stops (" + ((dashboard.favourites || []).length) + ")"),
                (dashboard.favourites || []).length ? dashboard.favourites.slice(0,3).map(function(f){ return h("div", { className:"tsf-mini-row", key:"fav-"+f.id }, h("button", { className:"tsf-link", onClick:function(){ setShowAccount(false); openDetail(f); } }, f.title), h("button", { className:"tsf-link tsf-link-danger", onClick:function(){ toggleSave(f.id); } }, "Remove")); })
                : h("div", { className:"tsf-card-meta" }, "No saved stops yet — tap Save on any stop to keep it.")
              ),
              h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Saved searches (" + ((dashboard.saved_searches || []).length) + ")"),
                (dashboard.saved_searches || []).length ? dashboard.saved_searches.slice(0,3).map(function(s){ return h("div", { className:"tsf-mini-row", key:"ss-"+s.id }, h("button", { className:"tsf-link", onClick:function(){ applySavedSearch(s.search_payload); } }, s.label), h("button", { className:"tsf-link tsf-link-danger", onClick:function(){ deleteSavedSearch(s.id); } }, "Delete")); })
                : h("div", { className:"tsf-card-meta" }, "No saved searches yet — save a search to revisit it later.")
              ),
              h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Recent submissions"),
                (dashboard.submissions || []).length ? dashboard.submissions.slice(0,3).map(function(s){ return h("div", { className:"tsf-mini-row", key:"sub-"+s.id }, h("button", { className:"tsf-link", disabled: !(s.post_url || s.post_id), onClick:function(){ if (s.post_url) { window.location.href = s.post_url; } else if (s.post_id) { setShowAccount(false); openDetail({ id: s.post_id, title: s.name || "Submission" }); } } }, (((s.name && String(s.name).toLowerCase() !== "listing") ? s.name : "") || ((s.title && String(s.title).toLowerCase() !== "listing") ? s.title : "") || "New stop") + (s.town || s.postcode ? (" — " + [s.town, s.postcode].filter(Boolean).join(" • ")) : "")), h("span", { className:"tsf-status-pill tsf-status-" + String(s.status || "pending").toLowerCase() }, s.status || "pending")); })
                : h("div", { className:"tsf-card-meta" }, "No submissions yet — add a stop to grow the map.")
              )
            ),
            h("div", { className:"tsf-card-actions" }, h("button", { className:"tsf-secondary", onClick:function(){ setShowSubmit(true); setShowAccount(false); } }, "Add stop"), h("button", { className:"tsf-secondary", onClick:logout }, "Log out"))
          ) : h("div", null,
            h("div", { className:"tsf-modal-head" }, h("h3", null, accountTab === "login" ? "Driver sign in" : "Create account"), h("button", { className:"tsf-close", onClick:function(){ setShowAccount(false); } }, "×")),
            accountTab === "login"
              ? h("form", { className:"tsf-auth-form", onSubmit:function(e){ authSubmit(e, "login"); } }, h("input", { name:"email", type:"email", placeholder:"Email", required:true }), h("input", { name:"password", type:"password", placeholder:"Password", required:true }), h("button", { type:"submit" }, "Log in"), h("button", { type:"button", className:"tsf-secondary", onClick:function(){ setAccountTab("register"); } }, "Create account"))
              : h("form", { className:"tsf-auth-form", onSubmit:function(e){ authSubmit(e, "register"); } }, h("input", { name:"display_name", placeholder:"Name" }), h("input", { name:"email", type:"email", placeholder:"Email", required:true }), h("input", { name:"password", type:"password", placeholder:"Password", required:true }), h("button", { type:"submit" }, "Create account"), h("button", { type:"button", className:"tsf-secondary", onClick:function(){ setAccountTab("login"); } }, "Back to sign in")),
            h("div", { className:"tsf-card-meta" }, "Browse first. Sign in when you want saved stops and saved searches.")
          )
        )
      ) : null;

      var submitModal = showSubmit ? h("div", { className:"tsf-modal-backdrop", onClick:function(){ setShowSubmit(false); } },
        h("div", { className:"tsf-modal", onClick:function(e){ e.stopPropagation(); } },
          h("div", { className:"tsf-modal-head" }, h("h3", null, "Add a truck stop"), h("button", { className:"tsf-close", onClick:function(){ setShowSubmit(false); } }, "×")),
          duplicateBlock ? h("div", { className:"tsf-duplicate" }, h("strong", null, "This stop already exists"), h("div", { className:"tsf-card-meta" }, duplicateBlock.title || ""), duplicateBlock.url ? h("a", { href:duplicateBlock.url, target:"_blank", rel:"noopener noreferrer" }, "Open existing listing") : null) : null,
          !token ? h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Sign in required"), h("div", { className:"tsf-card-meta" }, "You need an account to submit a new stop."), h("button", { onClick:function(){ setShowSubmit(false); setShowAccount(true); } }, "Open account"))
          : h("form", { className:"tsf-submit-form", onSubmit:submitListing },
              h("input", { name:"title", placeholder:"Stop name", required:true }),
              h("input", { name:"town_city", placeholder:"Town or city" }),
              h("input", { name:"postcode", placeholder:"Postcode" }),
              h("input", { name:"parking_type", placeholder:"Parking type" }),
              h("input", { name:"opening_hours", placeholder:"Opening hours" }),
              h("input", { name:"price_night", placeholder:"Night price" }),
              h("textarea", { name:"description", placeholder:"What drivers should know" }),
              h("div", { className:"tsf-check-grid" },
                h("label", null, h("input", { type:"checkbox", name:"showers" }), " Showers"),
                h("label", null, h("input", { type:"checkbox", name:"secure_parking" }), " Secure"),
                h("label", null, h("input", { type:"checkbox", name:"overnight_parking" }), " Overnight"),
                h("label", null, h("input", { type:"checkbox", name:"fuel" }), " Fuel"),
                h("label", null, h("input", { type:"checkbox", name:"food" }), " Food"),
                h("label", null, h("input", { type:"checkbox", name:"toilets" }), " Toilets")
              ),
              h("button", { type:"submit" }, "Submit for review")
            )
        )
      ) : null;

      return h("div", { className:"tsf-app-shell tsf-app-shell-v46" },
        h("div", { className:"tsf-map-stage" },
          (window.google && window.google.maps && window.TSF_CONFIG && window.TSF_CONFIG.googleMapsKey)
            ? h("div", { ref: mapEl, className:"tsf-map-canvas tsf-map-canvas-full" })
            : h("div", { className:"tsf-map-fallback tsf-map-canvas-full" }, h("div", { className:"tsf-card-title" }, "Map preview"), h("div", { className:"tsf-card-meta" }, "Add a Google Maps API key in plugin settings to enable the live map. Listings without saved coordinates will now try a postcode/town geocode fallback.")),
          mapPreview ? h("div", { className:"tsf-map-preview-card" },
            h("div", { className:"tsf-map-preview-head" },
              h("strong", null, mapPreview.title),
              h("button", { type:"button", className:"tsf-close tsf-map-preview-close", onClick:function(){ setMapPreview(null); } }, "×")
            ),
            h("div", { className:"tsf-card-meta" }, [mapPreview.town_city, mapPreview.postcode].filter(Boolean).join(" • ") || "Location pending"),
            Number(mapPreview.rating_count || 0) > 0
              ? h("div", { className:"tsf-rating-inline tsf-map-preview-rating" }, renderStarBoxes(mapPreview.rating), h("span", { className:"tsf-rating-inline-value" }, Number(mapPreview.rating || 0).toFixed(1)), h("span", { className:"tsf-rating-inline-count" }, "• " + Number(mapPreview.rating_count || 0) + " " + (Number(mapPreview.rating_count || 0) === 1 ? "review" : "reviews")))
              : h("div", { className:"tsf-card-meta" }, "No reviews yet"),
            h("div", { className:"tsf-card-actions" },
              h("button", { type:"button", className:"tsf-secondary", onClick:function(){ openDetail(mapPreview); setMapPreview(null); } }, "Open"),
              directionsUrlFor(mapPreview) ? h("a", { className:"tsf-secondary tsf-link-btn", href: directionsUrlFor(mapPreview), target:"_blank", rel:"noopener noreferrer" }, "Navigate") : null
            )
          ) : null,
          h("div", { className:"tsf-map-top-overlay tsf-map-top-overlay-compact" },
            h("div", { className:"tsf-top-search single" },
              h("input", { value: query, onChange:function(e){ setQuery(e.target.value); }, placeholder:"Search town or area", className:"tsf-search-input" }),
              h("button", { className:"tsf-primary", type:"button", onClick:runSearch }, loading ? "Searching..." : "Search")
            ),
            h("div", { className:"tsf-quick-actions" },
              h("button", { className:"tsf-secondary", onClick:requestLocation }, locating ? "Locating..." : (geo.enabled ? "Using your location" : "Use location")),
              h("button", { className:"tsf-secondary", onClick:function(){ setShowFilters(!showFilters); } }, showFilters ? "Hide filters" : "Filters")
            ),
            showFilters ? h("div", { className:"tsf-filter-panel tsf-filter-panel-compact" },
              h("div", { className:"tsf-filter-help" }, "Use location for nearest stops. Postcode and radius help refine a manual area search."),
              h("div", { className:"tsf-filter-row-mini" },
                h("input", { value: postcode, onChange:function(e){ setPostcode(e.target.value); }, placeholder:"Postcode" }),
                h("input", { value: radius, onChange:function(e){ setRadius(e.target.value); }, placeholder:"Miles", type:"number", min:"1" })
              ),
              h("div", { className:"tsf-check-grid" },
                h("label", null, h("input", { type:"checkbox", checked:filters.showers, onChange:function(e){ setFilters(Object.assign({}, filters, { showers:e.target.checked })); } }), " Showers"),
                h("label", null, h("input", { type:"checkbox", checked:filters.secure, onChange:function(e){ setFilters(Object.assign({}, filters, { secure:e.target.checked })); } }), " Secure"),
                h("label", null, h("input", { type:"checkbox", checked:filters.overnight, onChange:function(e){ setFilters(Object.assign({}, filters, { overnight:e.target.checked })); } }), " Overnight"),
                h("label", null, h("input", { type:"checkbox", checked:filters.fuel, onChange:function(e){ setFilters(Object.assign({}, filters, { fuel:e.target.checked })); } }), " Fuel"),
                h("label", null, h("input", { type:"checkbox", checked:filters.food, onChange:function(e){ setFilters(Object.assign({}, filters, { food:e.target.checked })); } }), " Food"),
                h("label", null, h("input", { type:"checkbox", checked:filters.featured, onChange:function(e){ setFilters(Object.assign({}, filters, { featured:e.target.checked })); } }), " Featured")
              )
            ) : null,
            message ? h("div", { className:"tsf-inline-message" }, message) : null
          ),
          h("div", { className:"tsf-map-bottom-overlay" }, h("strong", null, items.length + " stops"), h("span", null, sortBy === "distance" ? "Sorted by distance" : sortBy === "rating" ? "Sorted by rating" : sortBy === "reviews" ? "Sorted by reviews" : "Best match")),
        ),
        h("div", { className:"tsf-sheet-toggle-wrap" }, h("button", { className:"tsf-sheet-toggle", type:"button", onClick:function(){ setShowSheet(!showSheet); } }, showSheet ? "Hide results" : "Show results")),
        h("div", { className:"tsf-results-sheet tsf-bottom-sheet" + (showSheet ? " is-open" : " is-collapsed") },
          h("div", { className:"tsf-results-head" }, h("div", null, h("h3", null, "Stops nearby"), h("div", { className:"tsf-card-meta" }, communityStats.listings + " listings • " + communityStats.reviews + " reviews • " + communityStats.photos + " photos")), token ? h("button", { className:"tsf-secondary", onClick:saveSearch }, "Save search") : null),
          showSheet ? h("div", { className:"tsf-results-list" },
            items.length ? items.map(function(item){
              var saved = !!(dashboard.favourites || []).find(function(f){ return f.id === item.id; });
              return h("div", { className:"tsf-stop-card" + (selected && selected.id === item.id ? " is-selected" : ""), key:"item-"+item.id },
                h("div", { className:"tsf-stop-main" },
                  h("div", { className:"tsf-stop-top" }, h("div", { className:"tsf-card-heading" }, h("strong", null, item.title), h("span", { className:"tsf-listing-badge " + getListingBadge(item).className }, getListingBadge(item).label)), item.featured ? h("span", { className:"tsf-featured-badge" }, "Featured") : null),
                  h("div", { className:"tsf-stop-meta" }, [item.town_city, item.postcode].filter(Boolean).join(" • ") || "Location pending"),
                  h("div", { className:"tsf-stop-badges" }, item.showers ? h("span", null, "Showers") : null, item.secure_parking ? h("span", null, "Secure") : null, item.overnight_parking ? h("span", null, "Overnight") : null, item.fuel ? h("span", null, "Fuel") : null, item.food ? h("span", null, "Food") : null),
                  h("div", { className:"tsf-stop-stats" }, Number(item.rating_count || 0) > 0 ? h("span", { className:"tsf-rating-inline" }, renderStarBoxes(item.rating), h("span", { className:"tsf-rating-inline-value" }, Number(item.rating || 0).toFixed(1)), h("span", { className:"tsf-rating-inline-count" }, "• " + Number(item.rating_count || 0) + " " + (Number(item.rating_count || 0) === 1 ? "review" : "reviews"))) : h("span", null, "No reviews yet"), h("span", null, formatMiles(item.distance_miles)))
                ),
                h("div", { className:"tsf-stop-actions", onTouchEnd:function(e){ if (e && e.stopPropagation) e.stopPropagation(); }, onClick:function(e){ if (e && e.stopPropagation) e.stopPropagation(); } }, h("button", { className:"tsf-secondary", type:"button", onPointerDown:function(e){ openDetailTap(item, e); }, onTouchEnd:function(e){ openDetailTap(item, e); }, onClick:function(e){ openDetailTap(item, e); } }, "Details"), h("button", { type:"button", onPointerDown:function(e){ if (e && e.preventDefault) e.preventDefault(); if (e && e.stopPropagation) e.stopPropagation(); toggleSave(item.id); }, onTouchEnd:function(e){ if (e && e.preventDefault) e.preventDefault(); if (e && e.stopPropagation) e.stopPropagation(); toggleSave(item.id); }, onClick:function(e){ if (e && e.preventDefault) e.preventDefault(); if (e && e.stopPropagation) e.stopPropagation(); toggleSave(item.id); } }, saved ? "Saved" : "Save"))
              );
            }) : h("div", { className:"tsf-empty-card" }, "No stops found yet. Try a wider area or add a new stop.")
          ) : h("div", { className:"tsf-card-meta" }, selected ? (selected.title + " selected") : "Tap expand to browse results.")
        ),
        detail ? h("div", { className:"tsf-detail-drawer" },
          h("div", { className:"tsf-detail-head" }, h("div", null, h("h3", null, detail.listing.title), h("div", { className:"tsf-card-meta" }, [detail.listing.town_city, detail.listing.postcode].filter(Boolean).join(" • "))), h("button", { className:"tsf-close", type:"button", onClick:function(){ setDetail(null); } }, "×")),
          h("div", { className:"tsf-detail-body" },
            h("div", { className:"tsf-stop-badges" }, detail.listing.showers ? h("span", null, "Showers") : null, detail.listing.secure_parking ? h("span", null, "Secure") : null, detail.listing.overnight_parking ? h("span", null, "Overnight") : null, detail.listing.fuel ? h("span", null, "Fuel") : null, detail.listing.food ? h("span", null, "Food") : null, detail.listing.toilets ? h("span", null, "Toilets") : null),
            h("div", { className:"tsf-rating-box" },
              h("div", { className:"tsf-card-title" }, "Rating"),
              h("div", { className:"tsf-card-meta" }, "Average: " + (detail.listing.rating || 0) + " / 5 • " + (detail.listing.rating_count || 0) + " ratings"),
              h("div", { className:"tsf-rating-row tsf-rating-display-row" }, [1,2,3,4,5].map(function(star){
                var active = star <= Math.round(detail.listing.rating || 0);
                return h("span", { className: active ? "tsf-star-display is-active" : "tsf-star-display" }, "★");
              }))
            ),
            h("div", { className:"tsf-detail-grid" }, h("div", null, "Parking: " + (detail.details && detail.details.parking_type ? detail.details.parking_type : "N/A")), h("div", null, "Hours: " + (detail.details && detail.details.opening_hours ? detail.details.opening_hours : "N/A")), h("div", null, "Night price: " + (detail.details && detail.details.price_night ? ("£" + detail.details.price_night) : "N/A")), h("div", null, "Trust: " + (detail.listing.trust_label || "New"))),
            detail.listing.excerpt ? h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "About this stop"), h("div", null, detail.listing.excerpt)) : null,
            detail.reviews && detail.reviews.length ? h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Recent reviews"), detail.reviews.slice(0,5).map(function(r, idx){ return h("div", { className:"tsf-review", key:"rev-"+idx }, h("strong", null, "★ " + r.rating + "/5 • " + (r.author_name || "Driver")), r.review_text ? h("div", null, r.review_text) : null, r.review_tags && r.review_tags.length ? h("div", { className:"tsf-stop-badges" }, r.review_tags.map(function(tag, t){ return h("span", { key:"tag-"+idx+"-"+t }, tag); })) : null); })) : null,
            token ? (
              reviewSuccess ? h("div", { className:"tsf-card tsf-review-form tsf-review-form-confirmation", onClick:function(e){ if (e && e.stopPropagation) e.stopPropagation(); }, onTouchEnd:function(e){ if (e && e.stopPropagation) e.stopPropagation(); } },
                h("div", { className:"tsf-card-title" }, "Review submitted"),
                h("div", { className:"tsf-review-feedback tsf-review-success" }, reviewSuccess)
              ) : h("div", { className:"tsf-card tsf-review-form", onClick:function(e){ if (e && e.stopPropagation) e.stopPropagation(); }, onTouchEnd:function(e){ if (e && e.stopPropagation) e.stopPropagation(); } },
                h("div", { className:"tsf-card-title" }, "Leave a review"),
                h("div", { className:"tsf-card-meta" }, "Add a rating and short review."),
                reviewError ? h("div", { className:"tsf-review-feedback tsf-review-error" }, reviewError) : null,
                h("div", { className:"tsf-rating-row" }, [1,2,3,4,5].map(function(star){
                  return h("button", { type:"button", className: star <= reviewRating ? "tsf-star-btn is-active" : "tsf-star-btn", onPointerDown:function(e){ if (e && e.preventDefault) e.preventDefault(); if (e && e.stopPropagation) e.stopPropagation(); setReviewRating(star); if (reviewError) setReviewError(""); } }, "★");
                })),
                h("textarea", { id:"tsf-review-text", value: reviewText, onChange:function(e){ setReviewText(e.target.value); if (reviewError) setReviewError(""); }, placeholder:"What should drivers know about this stop?" }),
                h("div", { className:"tsf-card-actions" }, h("button", { type:"button", onPointerDown:function(e){ if (e && e.preventDefault) e.preventDefault(); if (e && e.stopPropagation) e.stopPropagation(); if (!reviewText || !String(reviewText).trim()) { setReviewError("Please add a review comment before posting."); setReviewSuccess(""); setMessage("Please add a review comment before posting."); return; } if (!reviewRating || reviewRating < 1) { setReviewError("Please select a star rating before posting."); setReviewSuccess(""); setMessage("Please select a star rating before posting."); return; } submitReview(detail.listing.id, reviewRating, reviewText); } }, reviewSubmitting ? "Posting..." : "Post review"))
              )
            ) : h("div", { className:"tsf-card-meta" }, "Log in to post a review."),
            detail.nearby && detail.nearby.length ? h("div", { className:"tsf-card" }, h("div", { className:"tsf-card-title" }, "Nearby alternatives"), detail.nearby.slice(0,4).map(function(n){ return h("div", { className:"tsf-mini-row", key:"near-"+n.id }, h("button", { className:"tsf-link", onClick:function(){ openDetail(n); } }, n.title), h("span", { className:"tsf-card-meta" }, (n.distance_miles || 0) + " mi")); })) : null,
            h("div", { className:"tsf-card-actions" }, directionsUrlFor(detail.listing) ? h("a", { className:"tsf-secondary tsf-link-btn", href: directionsUrlFor(detail.listing), target:"_blank", rel:"noopener noreferrer" }, "Navigate") : null, h("a", { className:"tsf-secondary tsf-link-btn", href: detail.listing.url, target:"_blank", rel:"noopener noreferrer" }, "Open full page"), h("button", { type:"button", onTouchEnd:function(e){ if (e && e.preventDefault) e.preventDefault(); toggleSave(detail.listing.id); }, onClick:function(e){ if (e && e.preventDefault) e.preventDefault(); toggleSave(detail.listing.id); } }, isSavedStop(detail.listing.id) ? "Saved" : "Save"))
          )
        ) : null,
        h("div", { className:"tsf-fab-top tsf-fab-user" }, h("button", { className:"tsf-fab", onClick:function(){ token ? loadDashboard(true, "overview") : setShowAccount(true); } }, "👤")),
        h("div", { className:"tsf-bottom-nav" },
          h("button", { className:"tsf-bottom-nav-btn", type:"button", onClick:function(){ window.scrollTo({ top: 0, behavior: 'smooth' }); } }, "Map"),
          h("button", { className:"tsf-bottom-nav-btn", type:"button", onClick:function(){ token ? loadDashboard(true, "saved") : setShowAccount(true); } }, "Saved"),
          h("button", { className:"tsf-bottom-nav-btn is-primary", type:"button", onClick:function(){ token ? setShowSubmit(true) : setShowAccount(true); } }, "Add stop")
        ),
        accountModal, submitModal
      );
    }

    ReactDOM.createRoot(document.getElementById("tsf-app")).render(h(App));
  });
})();