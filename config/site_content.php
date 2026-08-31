<?php

/*
|--------------------------------------------------------------------------
| Site Content
|--------------------------------------------------------------------------
|
| The copy the CMS edits, section by section. This file is the source of
| truth for three things: which fields exist, what a field falls back to
| before anyone has saved it, and what the update endpoint will accept.
| Adding a field here is all that is needed for it to be editable.
|
*/

return [

    'navbar' => [
        'stays' => 'Stays',
        'about' => 'About',
        'contact' => 'Contact',
    ],

    'home' => [
        'hero_heading' => 'Creating Memorable experiences',
        'hero_paragraph' => 'Book your perfect stay directly with us and skip the extra fees. Whether you\'re planning a weekend getaway or a long-term escape, Sobi Rentals offers stylish, fully-equipped accommodations tailored to your lifestyle.',
        'listings_label' => 'Our Listings',
        'listings_heading' => 'Choose Your Stay',
    ],

    'stays' => [
        'location_label' => 'Location',
        'location_placeholder' => 'Where are you going?',
        'check_in_label' => 'Check In',
        'check_in_placeholder' => 'Add date',
        'check_out_label' => 'Check Out',
        'check_out_placeholder' => 'Add date',
        'guests_label' => 'Guests',
        'guests_placeholder' => 'Add guests',
        'search_button_label' => 'Search',
        'default_results_heading' => 'All Stays',
    ],

    'about' => [
        'goal_heading' => 'Our Goal',
        'goal_paragraph_one' => 'Sobi Rentals Began With A Vision — To Redefine Short-term Stays By Blending The Reliability Of Hotels With The Charm And Comfort Of Home. What Started As A Single Rental Has Grown Into A Trusted Name For Modern Travelers Who Value Quality, Convenience, And Direct Communication.',
        'goal_paragraph_two' => 'We Understand That Every Traveler Is Different. That’s Why Each Of Our Spaces Is Curated With Care, Offering A Unique Atmosphere Without Compromising On The Essentials — Comfort, Cleanliness, And Connectivity.',
        'partner_button_label' => 'Partner With Us',
        'video_url' => 'https://www.youtube.com/watch?v=qzGxK6Uiu04',
        'feature_image' => null,

        'why_list_heading' => 'Why List With Sobi',
        // Rich text. The bullet list is what the public page turns into ticks.
        'why_list_body' => '<p>Everything you need to get your property booked, without the third-party fees.</p>'
            .'<ul>'
            .'<li>List your property in minutes</li>'
            .'<li>Get paid faster with direct payouts</li>'
            .'<li>Manage bookings, pricing, and calendars from one dashboard</li>'
            .'<li>Reach more guests across Sobi\'s network</li>'
            .'<li>Manage your listing on the go</li>'
            .'<li>Dedicated partner support</li>'
            .'</ul>',

        'faq_heading' => 'Common questions',
        'faqs' => [
            [
                'question' => 'What is the Sobi Partner Program? Who can apply?',
                'answer' => 'The Sobi Partner Program lets property owners list their space and start earning from bookings. Anyone with a property to rent can apply. We review new listings within a few business days.',
            ],
            [
                'question' => 'What is the difference between a full listing and a private stay?',
                'answer' => 'A full listing gives guests the entire property to themselves, while a private stay means guests book a private room within a shared home. You can offer either type when you list with Sobi.',
            ],
            [
                'question' => 'How long does it take for Sobi to review my listing?',
                'answer' => 'Most listings are reviewed and approved within 2-3 business days. We may reach out if we need more photos or details before your listing goes live.',
            ],
            [
                'question' => 'What rights does Sobi have to use my photos?',
                'answer' => 'You keep ownership of your photos. By listing with Sobi, you give us a licence to show them on the platform and in marketing so your property gets found.',
            ],
            [
                'question' => 'How will I get paid?',
                'answer' => 'Payouts go straight to your linked bank account shortly after each guest checks in, with no waiting on third-party processing.',
            ],
        ],
    ],

    'contact' => [
        'heading' => 'We\'re Here To Help — Anytime, Anywhere',
        'intro_paragraph' => 'Have A Question About Your Stay? Need Help With Booking?',
        'supporting_paragraph' => 'Whether It\'s Before You Arrive, During Your Visit, Or After You Check Out — Our Team Is Just A Message Away.',
    ],

];
