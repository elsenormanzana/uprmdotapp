<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @php $role = auth()->user()->role; @endphp
                @if ($role === 'admin')
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="magnifying-glass" :href="route('admin.infractions')" :current="request()->routeIs('admin.infractions')" wire:navigate>{{ __('Infractions') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('admin.permit-types')" :current="request()->routeIs('admin.permit-types')" wire:navigate>{{ __('Permit Types') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="map-pin" :href="route('admin.permit-zones')" :current="request()->routeIs('admin.permit-zones')" wire:navigate>{{ __('Permit Zones') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="user-plus" :href="route('admin.assign-permit')" :current="request()->routeIs('admin.assign-permit')" wire:navigate>{{ __('Assign Permit') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @elseif ($role === 'security_guard')
                    <flux:sidebar.group :heading="__('Guard')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('guard.dashboard')" :current="request()->routeIs('guard.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="document-plus" :href="route('guard.issue-infraction')" :current="request()->routeIs('guard.issue-infraction')">{{ __('Issue Infraction') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="qr-code" :href="route('guard.validate-student')" :current="request()->routeIs('guard.validate-student')" wire:navigate>{{ __('Validate Student') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group :heading="__('Student')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="truck" :href="route('student.vehicles')" :current="request()->routeIs('student.vehicles')" wire:navigate>{{ __('My Vehicles') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="currency-dollar" :href="route('student.infractions')" :current="request()->routeIs('student.infractions')" wire:navigate>{{ __('My Infractions') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="map" :href="route('student.campus-map')" :current="request()->routeIs('student.campus-map')" wire:navigate>{{ __('Campus Map') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('student.profile')" :current="request()->routeIs('student.profile')" wire:navigate>{{ __('My ID & QR') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>


        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @stack('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <script src="https://cdn.jsdelivr.net/npm/leaflet-draw@1.0.4/dist/leaflet.draw.js" crossorigin=""></script>
        <script>
        (function () {
            window._leafletMaps = window._leafletMaps || {};
            window._leafletPolygons = window._leafletPolygons || {};
            window._leafletDrawnItems = window._leafletDrawnItems || {};

            // Map color names to hex codes for Leaflet polygons
            function getColorHex(colorName) {
                var colorMap = {
                    'white': '#f3f4f6', // Light gray that's still clearly white
                    'yellow': '#facc15',
                    'orange': '#fb923c',
                    'green': '#22c55e',
                    'violet': '#a855f7',
                    'blue': '#3b82f6'
                };
                return colorMap[colorName] || colorMap['blue'];
            }
            
            // Get border color - dark gray for white (subtle accent), same as fill for others
            function getBorderColor(colorName, colorHex) {
                return colorName === 'white' ? '#6b7280' : colorHex; // Medium gray instead of black
            }

            // Create stripe pattern using SVG
            function createStripePattern(colorHex, patternId) {
                // Check if pattern already exists
                var existingPattern = document.getElementById(patternId);
                if (existingPattern) {
                    return 'url(#' + patternId + ')';
                }
                
                // Create SVG container if it doesn't exist
                var svgContainer = document.getElementById('leaflet-svg-patterns');
                if (!svgContainer) {
                    svgContainer = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    svgContainer.id = 'leaflet-svg-patterns';
                    svgContainer.setAttribute('style', 'position: absolute; width: 0; height: 0;');
                    svgContainer.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                    document.body.appendChild(svgContainer);
                }
                
                // Create defs if it doesn't exist
                var defs = svgContainer.querySelector('defs') || document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                if (!svgContainer.querySelector('defs')) {
                    svgContainer.appendChild(defs);
                }
                
                // Create pattern
                var pattern = document.createElementNS('http://www.w3.org/2000/svg', 'pattern');
                pattern.setAttribute('id', patternId);
                pattern.setAttribute('patternUnits', 'userSpaceOnUse');
                pattern.setAttribute('width', '8');
                pattern.setAttribute('height', '8');
                pattern.setAttribute('patternTransform', 'rotate(45)');
                
                // Create stripe line (thicker)
                var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', '0');
                line.setAttribute('y1', '0');
                line.setAttribute('x2', '0');
                line.setAttribute('y2', '8');
                line.setAttribute('stroke', colorHex);
                line.setAttribute('stroke-width', '4');
                
                pattern.appendChild(line);
                defs.appendChild(pattern);
                
                return 'url(#' + patternId + ')';
            }
            
            // Apply stripe pattern to polygon
            function applyStripePattern(polygon, colorHex, patternId, isWhite) {
                if (!polygon || !polygon._path) return;
                
                // Use subtle gray stripes for white polygons (not pure black)
                var stripeColor = isWhite ? '#9ca3af' : colorHex; // Medium-light gray instead of black
                var pattern = createStripePattern(stripeColor, patternId);
                var path = polygon._path;
                
                // Set fill to pattern
                path.setAttribute('fill', pattern);
                path.style.fill = pattern;
                // For white polygons, use lighter stripe opacity to keep it subtle
                path.setAttribute('fill-opacity', isWhite ? '0.4' : '0.5');
                path.style.fillOpacity = isWhite ? '0.4' : '0.5';
            }

            // Format popup content with permit types
            function formatPopupContent(zone) {
                var html = '<div style="padding: 8px; min-width: 200px;">';
                html += '<strong style="font-size: 14px; display: block; margin-bottom: 8px;">' + (zone.name || 'Zone') + '</strong>';
                if (zone.permitTypes && zone.permitTypes.length > 0) {
                    html += '<div style="margin-top: 8px;"><strong style="font-size: 12px; color: #666;">Allowed Permits:</strong><ul style="margin: 4px 0; padding-left: 20px;">';
                    zone.permitTypes.forEach(function(pt) {
                        var ptColorHex = getColorHex(pt.color);
                        html += '<li style="margin: 4px 0; display: flex; align-items: center; gap: 6px;">';
                        html += '<span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: ' + ptColorHex + ';"></span>';
                        html += '<span>' + pt.name + '</span>';
                        html += '</li>';
                    });
                    html += '</ul></div>';
                } else {
                    html += '<div style="margin-top: 8px; color: #999; font-size: 12px;">No permits assigned</div>';
                }
                html += '</div>';
                return html;
            }
            
            // Show zoom hint card with OS detection
            function showZoomHint() {
                var hintCard = document.getElementById('zoom-hint-card');
                var hintKey = document.getElementById('zoom-hint-key');
                var hintText = document.getElementById('zoom-hint-text');
                
                if (!hintCard || !hintKey || !hintText) return;
                
                // Detect OS
                var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0 || 
                           navigator.userAgent.toUpperCase().indexOf('MAC') >= 0;
                
                if (isMac) {
                    // Command key symbol (⌘) - Unicode character
                    hintKey.textContent = '⌘';
                    hintKey.style.fontSize = '14px';
                    hintKey.style.lineHeight = '1';
                    hintText.textContent = ' + scroll to zoom';
                } else {
                    // Control key text
                    hintKey.textContent = 'Ctrl';
                    hintText.textContent = ' + scroll to zoom';
                }
                
                // Show the card
                hintCard.style.display = 'block';
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    if (hintCard) {
                        hintCard.style.opacity = '0';
                        hintCard.style.transition = 'opacity 0.5s';
                        setTimeout(function() {
                            if (hintCard) {
                                hintCard.style.display = 'none';
                            }
                        }, 500);
                    }
                }, 5000);
            }

            function initMap(id, isCampus, forceRefresh) {
                var el = document.getElementById(id);
                if (!el || typeof window.L === 'undefined') return false;

                var draw = el.getAttribute('data-draw') === 'true';
                
                // Try external data element first (for permit-zones-map), then fall back to data-zones attribute
                var dataEl = document.getElementById(id + '-data');
                var raw = null;
                if (dataEl) {
                    raw = dataEl.getAttribute('data-zones');
                } else {
                    raw = el.getAttribute('data-zones');
                }
                var zones = [];
                if (raw && raw !== 'null' && raw !== '') {
                    try { 
                        zones = JSON.parse(raw);
                        if (!Array.isArray(zones)) zones = [];
                        console.log('Loaded ' + zones.length + ' zones for ' + id, zones);
                    } catch (e) {
                        console.warn('Failed to parse zones data for ' + id + ':', e, raw);
                        zones = [];
                    }
                } else {
                    console.log('No zones data found for ' + id + ', raw:', raw);
                }

                // If map exists and is working, update polygons without recreating map
                if (!forceRefresh && window._leafletMaps[id] && el.querySelector('.leaflet-tile-pane')) {
                    var map = window._leafletMaps[id];
                    // Clear existing polygons
                    if (window._leafletPolygons[id]) {
                        window._leafletPolygons[id].forEach(function(poly) {
                            map.removeLayer(poly);
                        });
                    }
                    window._leafletPolygons[id] = [];
                    // Add new polygons
                    var boundsGroup = [];
                    console.log('Updating map with ' + zones.length + ' zones');
                    zones.forEach(function (z, index) {
                        if (!z.polygon || !z.polygon.length) {
                            console.warn('Skipping zone with invalid polygon:', z);
                            return;
                        }
                        var colorHex = getColorHex(z.color || 'blue');
                        var borderColor = getBorderColor(z.color || 'blue', colorHex);
                        var polyOptions = { 
                            color: borderColor,
                            fillColor: colorHex,
                            fillOpacity: 0.3,
                            weight: z.color === 'white' ? 2.5 : 2
                        };
                        // For campus map, use different styling
                        if (isCampus) {
                            polyOptions.fillOpacity = 0.2;
                            polyOptions.weight = z.color === 'white' ? 2.5 : 2;
                        }
                        try {
                            var poly = window.L.polygon(z.polygon, polyOptions).addTo(map);
                            // Make saved polygons non-editable by draw control
                            poly.options.editable = false;
                            // Ensure polygon is not added to drawnItems
                            if (window._leafletDrawnItems && window._leafletDrawnItems[id]) {
                                if (window._leafletDrawnItems[id].hasLayer(poly)) {
                                    window._leafletDrawnItems[id].removeLayer(poly);
                                }
                            }
                            // Add stripe pattern
                            var patternId = 'stripe-' + id + '-' + index;
                            var isWhite = z.color === 'white';
                            setTimeout(function() {
                                applyStripePattern(poly, colorHex, patternId, isWhite);
                            }, 50);
                            
                            // Create popup with permit types
                            var popupContent = isCampus 
                                ? ((z.name ? z.name + ' — ' : '') + (z.typeName || ''))
                                : formatPopupContent(z);
                            poly.bindPopup(popupContent);
                            
                            window._leafletPolygons[id].push(poly);
                            boundsGroup.push(poly);
                            console.log('Added polygon ' + (index + 1) + '/' + zones.length + ' for zone:', z.name);
                        } catch (e) {
                            console.error('Error adding polygon for zone:', z.name, e);
                        }
                    });
                    console.log('Total polygons on map after update:', window._leafletPolygons[id].length);
                    // Fit map bounds to show all polygons if any exist
                    if (boundsGroup.length > 0) {
                        var group = new window.L.featureGroup(boundsGroup);
                        var bounds = group.getBounds();
                        if (bounds && bounds.isValid && bounds.isValid()) {
                            map.fitBounds(bounds, { padding: [20, 20] });
                        } else if (bounds) {
                            map.fitBounds(bounds, { padding: [20, 20] });
                        }
                    }
                    return true;
                }

                // Clean up existing broken map
                if (window._leafletMaps[id]) {
                    try {
                        window._leafletMaps[id].remove();
                    } catch (e) {}
                    delete window._leafletMaps[id];
                    delete window._leafletPolygons[id];
                    delete window._leafletDrawnItems[id];
                }

                // Clear any leftover Leaflet content
                el.innerHTML = '';
                delete el._leaflet_id;

                var map = window.L.map(id, {
                    scrollWheelZoom: false, // Disable scroll wheel zoom by default
                    zoomControl: true
                }).setView([18.21, -67.14], 15);
                window._leafletMaps[id] = map;
                window._leafletPolygons[id] = [];
                
                // Show zoom hint card for permit-zones-map
                if (id === 'permit-zones-map') {
                    showZoomHint();
                }
                
                // Enable zoom only when Command (Mac) or Control (Windows/Linux) is pressed
                // When scrolling without modifier, allow page scroll to table
                var mapContainer = map.getContainer();
                mapContainer.addEventListener('wheel', function(e) {
                    if (e.metaKey || e.ctrlKey) {
                        // Allow zoom when modifier key is pressed
                        e.preventDefault();
                        e.stopPropagation();
                        // Manually handle zoom
                        var delta = e.deltaY;
                        if (delta > 0) {
                            map.zoomOut();
                        } else {
                            map.zoomIn();
                        }
                    } else {
                        // Allow page scroll when modifier key is not pressed
                        // Don't prevent default - let the page scroll naturally
                        // This will scroll to the table below the map
                        // No preventDefault() call, so page scrolls normally
                    }
                }, { passive: false });

                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                var boundsGroup = [];
                console.log('Processing ' + zones.length + ' zones for map ' + id);
                zones.forEach(function (z, index) {
                    if (!z.polygon || !z.polygon.length) {
                        console.warn('Skipping zone with invalid polygon:', z);
                        return;
                    }
                    var colorHex = getColorHex(z.color || 'blue');
                    var borderColor = getBorderColor(z.color || 'blue', colorHex);
                    var polyOptions = { 
                        color: borderColor,
                        fillColor: colorHex,
                        fillOpacity: 0.3,
                        weight: z.color === 'white' ? 2.5 : 2
                    };
                    // For campus map, use different styling
                    if (isCampus) {
                        polyOptions.fillOpacity = 0.2;
                        polyOptions.weight = z.color === 'white' ? 2.5 : 2;
                    }
                    try {
                        var poly = window.L.polygon(z.polygon, polyOptions).addTo(map);
                        
                            // Add stripe pattern
                            var patternId = 'stripe-' + id + '-' + index;
                            var isWhite = z.color === 'white';
                            setTimeout(function() {
                                applyStripePattern(poly, colorHex, patternId, isWhite);
                            }, 50);
                            
                            // Make saved polygons non-editable by draw control
                        poly.options.editable = false;
                        // Ensure polygon is not added to drawnItems
                        if (window._leafletDrawnItems && window._leafletDrawnItems[id]) {
                            // Explicitly ensure it's not in drawnItems
                            if (window._leafletDrawnItems[id].hasLayer(poly)) {
                                window._leafletDrawnItems[id].removeLayer(poly);
                            }
                        }
                        
                        // Create popup with permit types
                        var popupContent = isCampus 
                            ? ((z.name ? z.name + ' — ' : '') + (z.typeName || ''))
                            : formatPopupContent(z);
                        poly.bindPopup(popupContent);
                        
                        window._leafletPolygons[id].push(poly);
                        boundsGroup.push(poly);
                        console.log('Added polygon ' + (index + 1) + '/' + zones.length + ' for zone:', z.name, 'with', z.polygon.length, 'points');
                    } catch (e) {
                        console.error('Error adding polygon for zone:', z.name, e);
                    }
                });
                console.log('Total polygons added to map:', window._leafletPolygons[id].length);

                    // Fit map bounds to show all polygons if any exist
                    if (boundsGroup.length > 0) {
                        var group = new window.L.featureGroup(boundsGroup);
                        var bounds = group.getBounds();
                        if (bounds && bounds.isValid && bounds.isValid()) {
                            map.fitBounds(bounds, { padding: [20, 20] });
                        } else if (bounds) {
                            map.fitBounds(bounds, { padding: [20, 20] });
                        }
                    }

                if (draw && window.L.Control && window.L.Control.Draw) {
                    var drawnItems = new window.L.FeatureGroup();
                    map.addLayer(drawnItems);
                    window._leafletDrawnItems[id] = drawnItems;
                    var drawControl = new window.L.Control.Draw({
                        draw: {
                            polygon: { shapeOptions: { color: '#3388ff' }, repeatMode: false },
                            polyline: false,
                            circle: false,
                            rectangle: false,
                            marker: false,
                            circlemarker: false
                        },
                        edit: { 
                            featureGroup: drawnItems,
                            remove: true
                        }
                    });
                    map.addControl(drawControl);
                    map.on('draw:created', function (e) {
                        var type = e.layerType;
                        var layer = e.layer;
                        if (type === 'polygon') {
                            drawnItems.clearLayers();
                            var latlngs = layer.getLatLngs()[0];
                            var arr = latlngs.map(function (ll) { return [ll.lat, ll.lng]; });
                            var inp = document.getElementById('permit-zones-polygon-json');
                            if (inp) {
                                inp.value = JSON.stringify(arr);
                                inp.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                            drawnItems.addLayer(layer);
                        }
                    });
                    // Ensure edit control only affects drawnItems, not saved polygons
                    map.on('draw:edited', function (e) {
                        var layers = e.layers;
                        layers.eachLayer(function(layer) {
                            // Only allow editing of layers in drawnItems
                            if (!drawnItems.hasLayer(layer)) {
                                console.warn('Attempted to edit saved polygon, ignoring');
                            }
                        });
                    });
                }

                // Force a resize after a short delay to ensure tiles load
                setTimeout(function () {
                    map.invalidateSize();
                }, 100);

                return true;
            }

            function run(forceRefresh) {
                var result1 = initMap('permit-zones-map', false, forceRefresh);
                var result2 = initMap('campus-map', true, forceRefresh);
                // If permit-zones-map failed and data element exists, retry after a short delay
                if (!result1 && document.getElementById('permit-zones-map-data')) {
                    setTimeout(function() { initMap('permit-zones-map', false, true); }, 200);
                }
                return result1 || result2;
            }

            function waitL(forceRefresh) {
                if (typeof window.L !== 'undefined') { run(forceRefresh); return; }
                var n = 0;
                var t = setInterval(function () {
                    if (typeof window.L !== 'undefined') { clearInterval(t); run(forceRefresh); return; }
                    if (++n > 50) clearInterval(t);
                }, 100);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() { waitL(false); });
            } else {
                waitL(false);
            }
            var campusMapTouchHandler = null;
            document.addEventListener('livewire:navigated', function() { 
                waitL(false);
                // Handle scroll lock for campus-map on mobile
                setTimeout(function() {
                    if (window.location.pathname.includes('campus-map') && window.innerWidth < 768) {
                        // Lock scroll - use viewport height to prevent scaling issues
                        var scrollY = window.scrollY;
                        document.body.style.overflow = 'hidden';
                        document.body.style.position = 'fixed';
                        document.body.style.width = '100%';
                        document.body.style.height = '100vh';
                        document.body.style.top = '-' + scrollY + 'px';
                        // Prevent touch scrolling except on map
                        if (!campusMapTouchHandler) {
                            campusMapTouchHandler = function(e) {
                                var target = e.target;
                                var isMap = target.closest('#campus-map') || target.closest('.leaflet-container') || target.closest('.leaflet-pane');
                                if (!isMap) {
                                    e.preventDefault();
                                }
                            };
                            document.addEventListener('touchmove', campusMapTouchHandler, { passive: false });
                        }
                    } else {
                        // Unlock scroll
                        var scrollY = document.body.style.top;
                        document.body.style.overflow = '';
                        document.body.style.position = '';
                        document.body.style.width = '';
                        document.body.style.height = '';
                        document.body.style.top = '';
                        if (scrollY) {
                            window.scrollTo(0, parseInt(scrollY || '0') * -1);
                        }
                        // Remove touch handler
                        if (campusMapTouchHandler) {
                            document.removeEventListener('touchmove', campusMapTouchHandler);
                            campusMapTouchHandler = null;
                        }
                    }
                }, 50);
            });
            document.addEventListener('livewire:initialized', function() { waitL(false); });
            document.addEventListener('livewire:updated', function () {
                // Always update polygons when Livewire updates (zones data may have changed)
                setTimeout(function() { 
                    // Check if permit-zones-map exists and update it
                    var mapId = 'permit-zones-map';
                    var el = document.getElementById(mapId);
                    var dataEl = document.getElementById(mapId + '-data');
                    if (el && dataEl && window._leafletMaps[mapId] && window.L) {
                        var raw = dataEl.getAttribute('data-zones');
                        var zones = [];
                        try { zones = raw ? JSON.parse(raw) : []; } catch (e) {}
                        var map = window._leafletMaps[mapId];
                        // Clear existing polygons
                        if (window._leafletPolygons[mapId]) {
                            window._leafletPolygons[mapId].forEach(function(poly) {
                                map.removeLayer(poly);
                            });
                        }
                        window._leafletPolygons[mapId] = [];
                        // Add new polygons
                        var boundsGroup = [];
                        zones.forEach(function (z, index) {
                            if (!z.polygon || !z.polygon.length) return;
                            var colorHex = getColorHex(z.color || 'blue');
                            var borderColor = getBorderColor(z.color || 'blue', colorHex);
                            var poly = window.L.polygon(z.polygon, { 
                                color: borderColor,
                                fillColor: colorHex,
                                fillOpacity: 0.3,
                                weight: z.color === 'white' ? 2.5 : 2,
                                editable: false
                            }).addTo(map);
                            
                            // Add stripe pattern
                            var patternId = 'stripe-' + mapId + '-' + index;
                            var isWhite = z.color === 'white';
                            setTimeout(function() {
                                applyStripePattern(poly, colorHex, patternId, isWhite);
                            }, 50);
                            
                            // Make saved polygons non-editable by draw control
                            poly.options.editable = false;
                            
                            // Create popup with permit types
                            var popupContent = formatPopupContent(z);
                            poly.bindPopup(popupContent);
                            
                            window._leafletPolygons[mapId].push(poly);
                            boundsGroup.push(poly);
                        });
                        // Fit map bounds to show all polygons if any exist
                        if (boundsGroup.length > 0) {
                            var group = new window.L.featureGroup(boundsGroup);
                            var bounds = group.getBounds();
                            if (bounds && bounds.isValid && bounds.isValid()) {
                                map.fitBounds(bounds, { padding: [20, 20] });
                            } else if (bounds) {
                                map.fitBounds(bounds, { padding: [20, 20] });
                            }
                        }
                    } else {
                        // Fallback to full refresh if map doesn't exist
                        waitL(true);
                    }
                }, 150);
            });

            // Function to refresh polygons on permit-zones-map
            function refreshPermitZonesPolygons() {
                var mapId = 'permit-zones-map';
                var el = document.getElementById(mapId);
                var dataEl = document.getElementById(mapId + '-data');
                if (el && dataEl && window._leafletMaps[mapId] && window.L) {
                    var raw = dataEl.getAttribute('data-zones');
                    var zones = [];
                    try { zones = raw ? JSON.parse(raw) : []; } catch (e) {
                        console.warn('Failed to parse zones data:', e);
                    }
                    var map = window._leafletMaps[mapId];
                    // Clear existing polygons
                    if (window._leafletPolygons[mapId]) {
                        window._leafletPolygons[mapId].forEach(function(poly) {
                            map.removeLayer(poly);
                        });
                    }
                    window._leafletPolygons[mapId] = [];
                    // Add new polygons
                    var boundsGroup = [];
                    zones.forEach(function (z) {
                        if (!z.polygon || !z.polygon.length) return;
                        var colorHex = getColorHex(z.color || 'blue');
                            var poly = window.L.polygon(z.polygon, { 
                                color: colorHex,
                                fillColor: colorHex,
                                fillOpacity: 0.3,
                                weight: 2,
                                editable: false
                            }).addTo(map);
                            // Make saved polygons non-editable by draw control
                            poly.options.editable = false;
                            if (z.name) poly.bindPopup(z.name);
                            window._leafletPolygons[mapId].push(poly);
                            boundsGroup.push(poly);
                    });
                    // Fit map bounds to show all polygons if any exist
                    if (boundsGroup.length > 0) {
                        var group = new window.L.featureGroup(boundsGroup);
                        var bounds = group.getBounds();
                        if (bounds && bounds.isValid && bounds.isValid()) {
                            map.fitBounds(bounds, { padding: [20, 20] });
                        } else if (bounds) {
                            map.fitBounds(bounds, { padding: [20, 20] });
                        }
                    }
                    // Clear drawn items after zone is created
                    if (window._leafletDrawnItems[mapId]) {
                        window._leafletDrawnItems[mapId].clearLayers();
                    }
                }
            }

            // Listen for zone creation/deletion/color updates to refresh polygons
            if (window.Livewire) {
                window.Livewire.on('permit-zone-created', function () {
                    setTimeout(refreshPermitZonesPolygons, 100);
                });
                window.Livewire.on('permit-zone-deleted', function () {
                    setTimeout(refreshPermitZonesPolygons, 100);
                });
                window.Livewire.on('permit-zone-color-updated', function () {
                    setTimeout(refreshPermitZonesPolygons, 100);
                });
            }
            // Also listen via DOM events as fallback
            document.addEventListener('livewire:init', function () {
                if (window.Livewire) {
                    window.Livewire.on('permit-zone-created', function () {
                        setTimeout(refreshPermitZonesPolygons, 100);
                    });
                    window.Livewire.on('permit-zone-deleted', function () {
                        setTimeout(refreshPermitZonesPolygons, 100);
                    });
                    window.Livewire.on('permit-zone-color-updated', function () {
                        setTimeout(refreshPermitZonesPolygons, 100);
                    });
                }
            });

            window.vehicleMakeDropdown = function () {
                return {
                    search: '',
                    open: false,
                    makes: [],
                    placeholder: 'Select make...',
                    logoCache: {},
                    logoFetchInFlight: {},
                    fetchJson: function (url) {
                        return fetch(url)
                            .then(function (res) { return res.text(); })
                            .then(function (text) {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    return null;
                                }
                            })
                            .catch(function () { return null; });
                    },
                    normalizeMakeKey: function (make) {
                        return String(make || '').trim().toLowerCase();
                    },
                wikidataSearch: function (make) {
                    var url = 'https://www.wikidata.org/w/api.php' +
                        '?action=wbsearchentities' +
                        '&search=' + encodeURIComponent(make) +
                        '&language=en&format=json&limit=1&origin=*';
                    return this.fetchJson(url)
                        .then(function (data) {
                            return data && data.search && data.search[0] ? data.search[0].id : null;
                        });
                },
                wikidataLogoFile: function (entityId) {
                    if (!entityId) return Promise.resolve(null);
                    var url = 'https://www.wikidata.org/w/api.php' +
                        '?action=wbgetentities' +
                        '&ids=' + encodeURIComponent(entityId) +
                        '&props=claims&format=json&origin=*';
                    return this.fetchJson(url)
                        .then(function (data) {
                            var entity = data && data.entities ? data.entities[entityId] : null;
                            var claims = entity && entity.claims ? entity.claims.P154 : null;
                            var claim = claims && claims[0] ? claims[0] : null;
                            var value = claim && claim.mainsnak && claim.mainsnak.datavalue
                                ? claim.mainsnak.datavalue.value
                                : null;
                            return value || null;
                        });
                },
                wikimediaFileUrl: function (fileName) {
                    if (!fileName) return Promise.resolve(null);
                    var url = 'https://commons.wikimedia.org/w/api.php' +
                        '?action=query' +
                        '&titles=File:' + encodeURIComponent(fileName) +
                        '&prop=imageinfo&iiprop=url&format=json&origin=*';
                    return this.fetchJson(url)
                        .then(function (data) {
                            var pages = data && data.query ? data.query.pages : null;
                            if (!pages) return null;
                            var firstKey = Object.keys(pages)[0];
                            var first = pages[firstKey];
                            return first && first.imageinfo && first.imageinfo[0]
                                ? first.imageinfo[0].url
                                : null;
                        });
                },
                    logoUrl: function (make, fetchIfMissing) {
                        var shouldFetch = fetchIfMissing !== false;
                        if (!make) return this.placeholderLogo('V');
                        var key = this.normalizeMakeKey(make);
                    var cachedUrl = this.logoCache[key];
                    if (cachedUrl) {
                        return cachedUrl;
                    }
                    if (shouldFetch && !this.logoFetchInFlight[key]) {
                        this.logoFetchInFlight[key] = true;
                        this.wikidataSearch(make)
                            .then(function (entityId) {
                                return this.wikidataLogoFile(entityId);
                            }.bind(this))
                            .then(function (fileName) {
                                return this.wikimediaFileUrl(fileName);
                            }.bind(this))
                            .then(function (url) {
                                if (url) {
                                    this.logoCache[key] = url;
                                }
                            }.bind(this))
                            .catch(function () {})
                            .finally(function () {
                                this.logoFetchInFlight[key] = false;
                            }.bind(this));
                        }
                        return this.placeholderLogo(String(make).charAt(0).toUpperCase());
                    },
                    placeholderLogo: function (letter) {
                        var safeLetter = (letter || 'V').replace(/[^A-Z0-9]/g, 'V');
                        var svg = [
                            '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24">',
                            '<rect width="24" height="24" rx="4" fill="#e5e7eb"/>',
                            '<text x="12" y="16" text-anchor="middle" font-size="12" fill="#6b7280" font-family="Arial, sans-serif">',
                            safeLetter,
                            '</text>',
                            '</svg>'
                        ].join('');
                        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
                    },
                    get filteredMakes() {
                        if (!this.search) return this.makes;
                        var query = this.search.toLowerCase();
                        return this.makes.filter(function (make) {
                            return make.toLowerCase().includes(query);
                        });
                    },
                    setMenuWidth: function () {
                        $nextTick(function () {
                            var btn = this.$refs.makeButton.querySelector('button');
                            var menus = document.querySelectorAll('[role=menu]');
                            if (btn && menus.length > 0) {
                                var btnWidth = btn.offsetWidth;
                                menus.forEach(function (m) {
                                    var mRect = m.getBoundingClientRect();
                                    var btnRect = btn.getBoundingClientRect();
                                    if (Math.abs(mRect.left - btnRect.left) < 10) {
                                        m.style.width = btnWidth + 'px';
                                        m.style.maxWidth = btnWidth + 'px';
                                    }
                                });
                            }
                        }.bind(this));
                    },
                    initFromDataset: function (el) {
                        try {
                            var makesScript = el.querySelector('script[data-makes-json]');
                            var rawMakes = makesScript ? makesScript.textContent.trim() : '[]';
                            this.makes = rawMakes ? JSON.parse(rawMakes) : [];
                        } catch (e) {
                            this.makes = [];
                        }
                        try {
                            var placeholderScript = el.querySelector('script[data-placeholder-json]');
                            var rawPlaceholder = placeholderScript ? placeholderScript.textContent.trim() : '"Select make..."';
                            this.placeholder = rawPlaceholder ? JSON.parse(rawPlaceholder) : 'Select make...';
                        } catch (e) {
                            this.placeholder = 'Select make...';
                        }
                    }
                };
            };
        })();
        </script>
        @fluxScripts
    </body>
</html>
