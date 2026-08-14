<?php
/**
 * Sapphire Luxury Aesthetics - Treatments Catalog API
 * Colombo 05, Sri Lanka
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$treatments = [
    [
        "id" => "hydrafacial-deluxe",
        "name" => "Medical HydraFacial Deluxe",
        "category" => "skin",
        "categoryName" => "Skin & Facial Aesthetics",
        "duration" => "45 - 60 Mins",
        "downtime" => "Zero Downtime",
        "doctor" => "Dr. Indi & Certified Aestheticians",
        "pricingEstimate" => "LKR 14,500 upwards"
    ],
    [
        "id" => "clinical-acne-scar",
        "name" => "Doctor-Led Clinical Acne & Scar Revision",
        "category" => "skin",
        "categoryName" => "Skin & Facial Aesthetics",
        "duration" => "60 Mins",
        "downtime" => "1 - 2 Days Mild Redness",
        "doctor" => "Dr. Indi (MBBS, Aesthetic Specialist)",
        "pricingEstimate" => "Customized Clinical Plan"
    ],
    [
        "id" => "carbon-laser-peel",
        "name" => "Hollywood Carbon Laser Peel",
        "category" => "laser",
        "categoryName" => "Laser Aesthetics",
        "duration" => "40 Mins",
        "downtime" => "None",
        "doctor" => "Laser Aesthetician & Dr. Indi",
        "pricingEstimate" => "LKR 12,000 upwards"
    ],
    [
        "id" => "prp-hair-restoration",
        "name" => "Medical PRP Hair Follicle Regeneration",
        "category" => "hair",
        "categoryName" => "Hair Restoration",
        "duration" => "50 Mins",
        "downtime" => "Minimal",
        "doctor" => "Dr. Indi",
        "pricingEstimate" => "Package options available"
    ],
    [
        "id" => "vampire-facial-prp",
        "name" => "Vampire Facial (PRP Skin Bio-Revitalization)",
        "category" => "anti-aging",
        "categoryName" => "Anti-Aging & Injectables",
        "duration" => "60 Mins",
        "downtime" => "24 - 48 Hours Rosy Glow",
        "doctor" => "Dr. Indi",
        "pricingEstimate" => "LKR 18,500 upwards"
    ],
    [
        "id" => "triple-laser-hair-removal",
        "name" => "Triple-Wavelength Painless Laser Hair Removal",
        "category" => "laser",
        "categoryName" => "Laser Aesthetics",
        "duration" => "15 - 90 Mins",
        "downtime" => "None",
        "doctor" => "Certified Laser Specialists",
        "pricingEstimate" => "Per Session & Full Body Packages"
    ]
];

echo json_encode([
    'status' => 'success',
    'count' => count($treatments),
    'clinic' => 'Sapphire Luxury Aesthetics - Colombo 05',
    'hotline' => '+94 77 714 3626',
    'treatments' => $treatments
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
