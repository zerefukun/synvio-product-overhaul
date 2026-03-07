jQuery(document).ready(function($) {
    // URL and navigation handling
    var currentSlug = (window.location.pathname.split('/').filter(Boolean).pop() || '');
    var baseSlugs = ['beton-cire-all-in-one-kant-klaar-', 'beton-cire-easyline-kant-klaar-', 'microcement-', 'lavasteen-gietvloer-', 'beton-cire-original-', 'metallic-velvet-4m2-pakket-'];
    // Exact slug matches that should also trigger color navigation (parent pages with different slug pattern)
    var exactSlugMap = {
        "microcement": "microcement-",
        "lavasteen-gietvloer": "lavasteen-gietvloer-",
        "beton-cire-easyline-all-in-one": "beton-cire-all-in-one-kant-klaar-",
        "beton-cire-easyline-kant-en-klaar": "beton-cire-easyline-kant-klaar-",
        "beton-cire-original": "beton-cire-original-",
        "metallic-stuc": "metallic-velvet-4m2-pakket-"
    };
    var isRelevantPage = baseSlugs.some(slug => currentSlug.startsWith(slug)) || currentSlug in exactSlugMap;
    // Prefixes where WAPO color values have trailing numbers that don't appear in the product slug
    // e.g. WAPO value "Cream Peony 23" → slug should be "cream-peony" not "cream-peony-23"
    var stripNumberSlugs = ['lavasteen-gietvloer-'];
    // Prefixes where WAPO values have leading number + separator that needs to be moved to end
    // e.g. WAPO value "1000 - Stone white" → slug should be "stone-white-1000" not "1000-stone-white"
    var swapNumberSlugs = ['beton-cire-original-'];

    // Utility functions
    function updatePlaceholder(addon, value) {
        var placeholderSpan = addon.find('h3.wapo-addon-title + .custom-placeholder');
        if (!placeholderSpan.length) {
            placeholderSpan = $('<span class="custom-placeholder"></span>').insertAfter(addon.find('h3.wapo-addon-title'));
        }
        placeholderSpan.text(value || 'Geen kleur');
    }

    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    // Strip trailing number from a WAPO color value (e.g. "Cream Peony 23" → "Cream Peony")
    function stripTrailingNumber(text) {
        return text.replace(/\s+\d+$/, '');
    }

    // slugOverrides — applied universally in colorToSlug() for WAPO values that don't match product slugs
    var slugOverrides = {
        'peachblossem-light-1016': 'peach-blossom-light-1016',
        'peachblossem-dark-1017': 'peach-blossom-dark-1017',
        'sunday-yellow-1030': 'sunday-1030',
        'sahara-dust-yellow-1031': 'sahara-dust-1031',
        'deep-earth-green-1034': 'deep-earth-1034',
        'teal-grey-1054': 'telegrey-1054',
        'ros': 'rose'  // "Rosé" → slugify strips é → "ros", actual slug is "rose"
    };
    // Move leading number to end (e.g. "1000 - Stone white" → "stone-white-1000")
    function swapLeadingNumber(text) {
        var match = text.match(/^(\d+)\s*-\s*(.+)$/);
        if (match) {
            return slugify(match[2]) + '-' + match[1];
        }
        return slugify(text);
    }

    // Get the active base slug for the current page
    function getActiveSlug() {
        return baseSlugs.find(function(slug) { return currentSlug.startsWith(slug); }) || exactSlugMap[currentSlug];
    }

    // Check if current page uses a prefix that needs trailing numbers stripped from WAPO values
    function shouldStripNumbers() {
        return stripNumberSlugs.indexOf(getActiveSlug()) !== -1;
    }

    // Check if current page uses a prefix that needs leading numbers swapped to end
    function shouldSwapNumbers() {
        return swapNumberSlugs.indexOf(getActiveSlug()) !== -1;
    }

    // Transform a WAPO color value to a URL-safe slug based on the active prefix rules
    function colorToSlug(rawValue) {
        var result;
        if (shouldSwapNumbers()) {
            result = swapLeadingNumber(rawValue);
        } else if (shouldStripNumbers()) {
            result = slugify(stripTrailingNumber(rawValue));
        } else {
            result = slugify(rawValue);
        }
        // Apply overrides for WAPO values that don't match product slugs
        return slugOverrides[result] || result;
    }

    // Color addon initialization and handling
    $('.yith-wapo-addon-type-color').each(function() {
        updatePlaceholder($(this));
    });

    if (isRelevantPage) {
        var colorSlug = baseSlugs.reduce((slug, baseSlug) => slug.replace(baseSlug, ''), currentSlug);
        
        $('.yith-wapo-addon-type-color .yith-wapo-option-value').each(function() {
            var inputValue = $(this).val();
            // Transform WAPO value to slug for comparison (handles trailing/leading number prefixes)
            var compareValue = colorToSlug(inputValue);
            if (compareValue === colorSlug) {
                $(this).prop('checked', true);
                $(this).closest('.yith-wapo-option').addClass('selected');
                updatePlaceholder($(this).closest('.yith-wapo-addon-type-color'), inputValue);
            }
        });
    }

    // Color addon change handlers
    $('.yith-wapo-addon-type-color').on('change', '.yith-wapo-option-value', function() {
        var $addon = $(this).closest('.yith-wapo-addon-type-color');
        var $option = $(this).closest('.yith-wapo-option');
        
        $addon.find('.yith-wapo-option').removeClass('selected');
        $addon.find('.yith-wapo-option-value').not(this).prop('checked', false);
        
        if (this.checked) {
            $option.addClass('selected');
            var textToCopy = $option.find('small').text().trim();
            updatePlaceholder($addon, textToCopy);
            
            if (isRelevantPage) {
                var baseUrl = 'https://beton-cire-webshop.nl/';
                var rawValue = $(this).val();
                // Transform WAPO value to slug (handles trailing/leading number prefixes)
                var inputSlug = colorToSlug(rawValue);
                var usedBaseSlug = baseSlugs.find(slug => currentSlug.startsWith(slug)) || exactSlugMap[currentSlug];
                var newUrl = baseUrl + usedBaseSlug + inputSlug + '/';
                window.location.href = newUrl;
            }
        } else {
            updatePlaceholder($addon);
        }
    });

    // Addon 75 initialization and handling
    $('#yith-wapo-addon-75').each(function() {
        var $addon = $(this);
        var $placeholderSpan = $addon.find('h3.wapo-addon-title + .custom-placeholder');
        if (!$placeholderSpan.length) {
            $placeholderSpan = $('<span class="custom-placeholder">Geen keuze</span>').insertAfter($addon.find('h3.wapo-addon-title'));
        }
    });

    $('#yith-wapo-addon-75').on('change', '.yith-wapo-option-value', function() {
        var $addon = $(this).closest('.yith-wapo-addon');
        var $placeholderSpan = $addon.find('h3.wapo-addon-title + .custom-placeholder');
        
        if (this.checked) {
            var textToCopy = $(this).closest('.yith-wapo-option').find('.description').text().trim();
            $placeholderSpan.text(textToCopy);
        } else {
            $placeholderSpan.text('Geen keuze');
        }
    });

    // Product redirection handlers
    $(document).on('click', '#yith-wapo-option-90-1', function() {
        window.location.href = "https://beton-cire-webshop.nl/beton-cire-easyline-all-in-one/";
    });
    
    $(document).on('click', '#yith-wapo-option-37-1', function() {
        window.location.href = "https://beton-cire-webshop.nl/beton-cire-easyline-kant-en-klaar/";
    });

    // Price label modification
    if ($('.price.product-page-price').length) {
        var newSpan = $('<span class="oz-vanaf">vanaf</span>');
        $('.price.product-page-price .woocommerce-Price-currencySymbol').before(newSpan);
    }

    // Tooltip functionality
    $('.yith-wapo-addon.yith-wapo-addon-type-label:not(#yith-wapo-addon-75)').each(function() {
        var $addon = $(this);
        var $title = $addon.find('h3.wapo-addon-title');
        var $description = $addon.find('.wapo-addon-description');

        if ($title.length && $description.length) {
            var descriptionHtml = $description.html();
            var tooltipWrapper = $('<div class="yith-wapo-option oztooltipdiv"></div>').append(
                $('<i class="tooltip-icon fas fa-question-circle"></i>'),
                $('<span class="tooltip position-top tooltipstered"></span>').append(
                    $('<span>').html(descriptionHtml)
                )
            );

            $title.after(tooltipWrapper);
            $description.hide();
        }
    });

    // Checkbox functionality
    function preventUnchecking() {
        $('.yith-wapo-addon.yith-wapo-addon-type-label input[type="checkbox"]:checked').each(function() {
            $(this).off('click').on('click', function(e) {
                e.preventDefault();
            });
        });
    }

    preventUnchecking();

    $('.yith-wapo-addon.yith-wapo-addon-type-label input[type="checkbox"]').on('change', function() {
        if ($(this).is(':checked')) {
            var $group = $(this).closest('.options');
            if ($group.length) {
                $group.find('input[type="checkbox"]').not(this).prop('checked', false).off('click');
                preventUnchecking();
            }
        }
    });

    // TrustIndex functionality
    function isTrustIndexLoadable() {
        var userAgent = navigator.userAgent;
        var pageSpeedTesters = [
            'Google Page Speed Insights',
            'GTmetrix',
            'Pingdom',
        ];

        return !pageSpeedTesters.some(tester => userAgent.indexOf(tester) !== -1);
    }

    if (isTrustIndexLoadable()) {
        var trustIndexContainer = document.getElementById('trustindex-script-container');
        if (trustIndexContainer) {
            var trustIndexScript = document.createElement('script');
            trustIndexScript.src = 'https://cdn.trustindex.io/loader.js?c754f892650e180cb696989cccc';
            trustIndexScript.defer = true;
            trustIndexScript.async = true;
            trustIndexContainer.appendChild(trustIndexScript);
        }
    }
});