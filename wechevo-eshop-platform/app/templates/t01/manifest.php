<?php
/* Template 01 · Bento Market — design tokens the shop admin may customise safely. */
return [
    'name' => 'Bento Market',
    'fonts' => 'https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wdth,wght@8..144,25..151,300..1000&display=swap',
    'tokens' => [
        'background' => ['label' => 'Φόντο σελίδας', 'default' => '#17195A'],
        'background_alt' => ['label' => 'Φόντο δευτερευόντων ζωνών', 'default' => '#22257A'],
        'text' => ['label' => 'Κείμενο πάνω στο φόντο', 'default' => '#F6F3FF'],
        'muted' => ['label' => 'Δευτερεύον κείμενο', 'default' => '#B9B8E6'],
        'surface' => ['label' => 'Κάρτες / πάνελ', 'default' => '#FFFFFF'],
        'surface_text' => ['label' => 'Κείμενο σε κάρτες', 'default' => '#17195A'],
        'primary' => ['label' => 'Κύριο χρώμα (κουμπιά αγοράς)', 'default' => '#FF7A59'],
        'secondary' => ['label' => 'Δευτερεύον χρώμα', 'default' => '#FFD95E'],
        'accent' => ['label' => 'Χρώμα έμφασης', 'default' => '#8FE3C8'],
        'accent2' => ['label' => 'Χρώμα έμφασης 2', 'default' => '#C9B8FF'],
        'button_text' => ['label' => 'Κείμενο κουμπιών', 'default' => '#17195A'],
        'link' => ['label' => 'Σύνδεσμοι', 'default' => '#FFD95E'],
    ],
    // pairs that must stay readable; the appearance screen warns below 4.5:1
    'contrast_pairs' => [['text', 'background'], ['surface_text', 'surface'], ['button_text', 'primary']],
];
