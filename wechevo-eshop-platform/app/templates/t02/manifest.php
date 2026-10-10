<?php
/* Template 02 · Swiss Clarity — strict grid, search-first, hairlines, monospaced prices. Reuses t01 page views with its own layout & stylesheet. */
return [
    'name' => 'Swiss Clarity',
    'fonts' => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap',
    'tokens' => [
        'background' => ['label' => 'Φόντο σελίδας', 'default' => '#FFFFFF'],
        'background_alt' => ['label' => 'Φόντο ζωνών', 'default' => '#F3F4F6'],
        'text' => ['label' => 'Κείμενο', 'default' => '#121417'],
        'muted' => ['label' => 'Δευτερεύον κείμενο', 'default' => '#5E6570'],
        'surface' => ['label' => 'Κάρτες / πάνελ', 'default' => '#FFFFFF'],
        'surface_text' => ['label' => 'Κείμενο σε κάρτες', 'default' => '#121417'],
        'primary' => ['label' => 'Κύριο χρώμα (κουμπιά)', 'default' => '#0B4FD6'],
        'secondary' => ['label' => 'Γραμμές πλέγματος', 'default' => '#E3E5E8'],
        'accent' => ['label' => 'Φόντο εικόνων', 'default' => '#F3F4F6'],
        'accent2' => ['label' => 'Επισήμανση', 'default' => '#EAF0FF'],
        'button_text' => ['label' => 'Κείμενο κουμπιών', 'default' => '#FFFFFF'],
        'link' => ['label' => 'Σύνδεσμοι', 'default' => '#0B4FD6'],
    ],
    'contrast_pairs' => [['text', 'background'], ['surface_text', 'surface'], ['button_text', 'primary']],
];
