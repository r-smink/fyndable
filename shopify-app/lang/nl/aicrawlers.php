<?php

return [
    'intro' => 'Bekijk welke AI-crawlers je winkel mogen lezen. Zoekcrawlers bepalen of ChatGPT, Claude en Perplexity je producten kunnen tonen en aanraden; trainingscrawlers verzamelen alleen content om AI-modellen te trainen. Je llms.txt vertelt de toegestane crawlers wat het belangrijkst is.',
    'default_note' => 'De standaard robots.txt van Shopify staat al deze crawlers toe. Fyndable controleert je live robots.txt en past je thema niet aan.',
    'bots_title' => 'AI-crawlers',
    'check' => 'Nu controleren',

    'js' => [
        'purpose_search' => 'AI-zoeken',
        'purpose_user' => 'Bezoek op verzoek',
        'purpose_training' => 'Modeltraining',
        'purpose_search_help' => 'Blokkeren verbergt je winkel in de antwoorden van deze AI-zoekmachine.',
        'purpose_user_help' => 'Haalt een pagina op als een gebruiker de assistent ernaar vraagt.',
        'purpose_training_help' => 'Verzamelt content om AI-modellen te trainen. Blokkeren heeft geen invloed op zoekresultaten.',
        'col_bot' => 'Crawler',
        'col_company' => 'Bedrijf',
        'col_purpose' => 'Doel',
        'col_live' => 'Live robots.txt',
        'live_allowed' => 'Toegestaan',
        'live_blocked' => 'Geblokkeerd',
        'search_allowed' => 'Alle AI-zoekcrawlers mogen je winkel lezen.',
        'search_blocked' => 'Je robots.txt blokkeert AI-zoekcrawlers: {bots}. Je winkel verschijnt dan niet in hun antwoorden.',
        'live_error' => 'De live robots.txt kon niet worden gelezen ({error}). Bij winkels met een wachtwoord is die vaak afgeschermd.',
    ],
];
