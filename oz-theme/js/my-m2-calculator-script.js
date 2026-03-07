jQuery(document).ready(function($) {
    console.log("m² Calculator script loaded");
    if (M2CalculatorParams.isEnabled) {
        console.log("m² Calculator is enabled");
        
        var customStyle = `
            <style>
                .ux-quantity.quantity {
                    position: relative;
                    padding-right: 25px;
                }
                .ux-quantity.quantity::after {
                    content: 'm²';
                    position: absolute;
                    right: 0;
                    font-size: 14px;
                    color: #666;
                    top: 50%;
                    transform: translateY(-50%);
                }
            </style>
        `;

        $('head').append(customStyle);
    } else {
        console.log("m² Calculator is not enabled");
    }
});