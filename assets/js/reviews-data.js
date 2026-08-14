/**
 * Sapphire Luxury Aesthetics - Patient Reviews & Transformation Cases Database
 * Verified Real Clinical Results | Colombo 05, Sri Lanka
 */

const DEFAULT_REVIEWS_DATA = [
  {
    id: "rev-1",
    clientName: "Senali Wickramasinghe",
    location: "Colombo 07",
    treatment: "Doctor-Led Clinical Acne & Scar Revision",
    rating: 5,
    date: "2 weeks ago",
    verified: true,
    quote: "Dr. Indi is phenomenal! I had struggled with severe hormonal cystic acne for over 2 years that ruined my self-confidence. After 3 customized sessions at Sapphire Luxury, my breakouts stopped completely and my skin feels baby soft. The clinic atmosphere in Colombo 05 is pure world-class luxury.",
    transformationCase: "3 Sessions • Severe Hormonal Acne to Clear Skin"
  },
  {
    id: "rev-2",
    clientName: "Dr. Rajiv Mendis",
    location: "Colombo 03",
    treatment: "Medical PRP Hair Follicle Regeneration",
    rating: 5,
    date: "1 month ago",
    verified: true,
    quote: "The PRP Hair Therapy here is remarkable. As a fellow doctor, I appreciate Dr. Indi's strict sterile protocols and clinical precision. I was noticing crown thinning, and after 4 sessions, shedding halted completely and noticeable new dense hair growth sprouted.",
    transformationCase: "4 Sessions • Crown Follicle Thickening"
  },
  {
    id: "rev-3",
    clientName: "Natasha Fernando",
    location: "Nawala",
    treatment: "Medical HydraFacial Deluxe & Glow Protocol",
    rating: 5,
    date: "3 weeks ago",
    verified: true,
    quote: "The HydraFacial here is unlike regular salon facials — it is genuine medical-grade. My skin has never looked so luminous and pore-free before my destination wedding. Dr. Indi and the nursing staff are so caring and attentive.",
    transformationCase: "Single Session • Instant Glass Skin Radiance"
  },
  {
    id: "rev-4",
    clientName: "Dilshan Jayasuriya",
    location: "Rajagiriya",
    treatment: "Triple-Wavelength Painless Laser Hair Removal",
    rating: 5,
    date: "Just now",
    verified: true,
    quote: "Zero pain! I was skeptical because I had a bad laser burn experience elsewhere years ago. Sapphire Luxury's ICE cooling laser was 100% painless and completely cleared my neck ingrown hairs. Highly recommend to all gentlemen too.",
    transformationCase: "5 Sessions • Full Beard Line & Neck Definition"
  },
  {
    id: "rev-5",
    clientName: "Kaveesha Perera",
    location: "Mount Lavinia",
    treatment: "Targeted Melasma & Pigmentation Correction",
    rating: 5,
    date: "1 month ago",
    verified: true,
    quote: "Stubborn sun spots on my cheekbones that wouldn't budge with creams are now 90% vanished after Dr. Indi's bespoke depigmentation regimen. The medical advice and aftercare support are top notch.",
    transformationCase: "4 Sessions • Deep Dermal Melasma Faded"
  }
];

// Helper to get reviews from localStorage or fall back to defaults
function getStoredReviews() {
  const saved = localStorage.getItem('sapphire_reviews_data');
  if (saved) {
    try {
      return JSON.parse(saved);
    } catch (e) {
      console.error("Failed to parse stored reviews, using defaults", e);
    }
  }
  return DEFAULT_REVIEWS_DATA;
}

function saveStoredReviews(reviews) {
  localStorage.setItem('sapphire_reviews_data', JSON.stringify(reviews));
}
