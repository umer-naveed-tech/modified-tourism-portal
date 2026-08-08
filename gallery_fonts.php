<?php
// gallery_fonts.php
//
// Shared list of fonts offered for the Hotel Gallery -- included by
// agent_hotel_form.php (the picker), save_gallery.php (validation),
// and hotel_gallery.php / gallery_renderer.php (loading the right
// Google Fonts URL). Keeping this in one place means adding a font
// later only needs one edit, not three.

function galleryFontChoices() {
    return [
        'Inter'               => 'Inter:wght@400;600;700',
        'Playfair Display'    => 'Playfair+Display:wght@600;700;800',
        'Georgia'             => null, // web-safe, no Google Fonts needed
        'Poppins'             => 'Poppins:wght@400;600;700',
        'Montserrat'          => 'Montserrat:wght@400;600;700',
        'Merriweather'        => 'Merriweather:wght@400;700',
        'Roboto'              => 'Roboto:wght@400;600;700',
        'Lora'                => 'Lora:wght@400;600;700',
        'Raleway'             => 'Raleway:wght@400;600;700',
        'Nunito'              => 'Nunito:wght@400;600;700',
        'Oswald'              => 'Oswald:wght@400;600;700',
        'Cormorant Garamond'  => 'Cormorant+Garamond:wght@500;600;700',
        'Crimson Text'        => 'Crimson+Text:wght@400;600;700',
        'Work Sans'           => 'Work+Sans:wght@400;600;700',
        'Quicksand'           => 'Quicksand:wght@400;600;700',
        'DM Serif Display'    => 'DM+Serif+Display:wght@400',
        'Josefin Sans'        => 'Josefin+Sans:wght@400;600;700',
        'Libre Baskerville'   => 'Libre+Baskerville:wght@400;700',
        'Source Sans Pro'     => 'Source+Sans+Pro:wght@400;600;700',
        'Space Grotesk'       => 'Space+Grotesk:wght@400;600;700',
    ];
}

function isValidGalleryFont($font) {
    return array_key_exists($font, galleryFontChoices());
}