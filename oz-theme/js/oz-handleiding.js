jQuery(document).ready(function($) {
    if (typeof customContentParams !== 'undefined' && customContentParams.isEnabled) {
        var contentData = customContentParams.dynamicContent;

        if (customContentParams.enableOzHandleiding) {
            console.log("Oz Handleiding is enabled");

            var customContent = '<div class="handleiding-main">';
            customContent += '<div class="handleiding-container">';
            customContent += '<div class="handleiding-img"><img src="' + contentData.image + '" alt="Handleiding"></div>';
            customContent += '<div class="handleiding-text-container"><div class="handleiding-text"><h2>' + contentData.title + '</h2><p>' + contentData.text + '</p></div>';
            customContent += '<div class="handleiding-button"><a href="' + contentData.link + '" class="button" target="_blank">Handleiding lezen</a></div>';
            customContent += '</div></div></div>';

            $('.woocommerce-tabs.wc-tabs-wrapper.container.tabbed-content').before(customContent);
        } else {
            console.log("Oz Handleiding is not enabled");
        }
    }
        // Event delegation to prevent dragging for dynamically added images
        $(document).on('dragstart', '.handleiding-img img', function(event) {
            event.preventDefault();
            console.log("Preventing drag on dynamically added image");
        });
    
        // Event delegation to prevent dragging for dynamically added anchor tags
        $(document).on('dragstart', '.handleiding-button a', function(event) {
            event.preventDefault();
            console.log("Preventing drag on dynamically added anchor tag");
        });
});