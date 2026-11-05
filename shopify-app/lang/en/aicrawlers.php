<?php

return [
    'intro' => 'See which AI crawlers may read your store. Search crawlers decide whether ChatGPT, Claude and Perplexity can show and recommend your products; training crawlers only collect content to train AI models. Your llms.txt tells the allowed crawlers what matters most.',
    'default_note' => 'Shopify\'s standard robots.txt allows all of these crawlers. Fyndable checks your live robots.txt and does not change your theme.',
    'bots_title' => 'AI crawlers',
    'check' => 'Check now',

    'js' => [
        'purpose_search' => 'AI search',
        'purpose_user' => 'Visits on request',
        'purpose_training' => 'Model training',
        'purpose_search_help' => 'Blocking hides your store from answers in this AI search engine.',
        'purpose_user_help' => 'Fetches a page when a user asks the assistant about it.',
        'purpose_training_help' => 'Collects content to train AI models. Blocking does not affect search results.',
        'col_bot' => 'Crawler',
        'col_company' => 'Company',
        'col_purpose' => 'Purpose',
        'col_live' => 'Live robots.txt',
        'live_allowed' => 'Allowed',
        'live_blocked' => 'Blocked',
        'search_allowed' => 'All AI search crawlers may read your store.',
        'search_blocked' => 'Your robots.txt blocks AI search crawlers: {bots}. Your store will not appear in their answers.',
        'live_error' => 'Could not read the live robots.txt ({error}). Password-protected stores often hide it.',
    ],
];
