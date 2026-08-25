/**
 * Sapphire Luxury Aesthetics - Clinical Reels & Video Stories Database
 * Live Clinical Procedure Videos & Treatment Reels | Colombo 05, Sri Lanka
 */

const DEFAULT_REELS_DATA = [
  {
    id: "reel-1",
    title: "Inside Sapphire Luxury Sanctuary & Consultation",
    category: "CLINICAL VLOG",
    tag: "LIVE PROCEDURE REEL",
    videoUrl: "Video/clinic-reel.mp4",
    poster: "Photos/Logo.jpg",
    description: "Physician-led diagnostic consultation & advanced sterile aesthetic procedures in Colombo 05.",
    treatmentId: "hydrafacial-deluxe",
    views: "2.4K",
    featured: true
  },
  {
    id: "reel-2",
    title: "Clinical Acne & Deep Scar Revision Protocol",
    category: "ACNE REVISION",
    tag: "DOCTOR SPECIALTY",
    videoUrl: "Video/clinic-reel.mp4",
    poster: "Photos/treatments/acne.jpg",
    description: "Multi-layered medical chemical peel & microneedling extraction for active cystic breakouts.",
    treatmentId: "clinical-acne-scar",
    views: "1.8K",
    featured: true
  },
  {
    id: "reel-3",
    title: "Medical HydraFacial 4-Stage Vortex Glow",
    category: "HYDRAFACIAL",
    tag: "MOST POPULAR",
    videoUrl: "Video/clinic-reel.mp4",
    poster: "Photos/treatments/670314283_122212625606360851_3673288927463636939_n.jpg",
    description: "Watch deep sebum and blackhead extraction followed by hyaluronic antioxidant infusion.",
    treatmentId: "hydrafacial-deluxe",
    views: "3.5K",
    featured: true
  },
  {
    id: "reel-4",
    title: "PRP Stem Hair Density & Follicle Restoration",
    category: "HAIR RESTORATION",
    tag: "ADVANCED PRP",
    videoUrl: "Video/clinic-reel.mp4",
    poster: "Photos/treatments/743186544_122221328054360851_3914819105912498017_n.jpg",
    description: "Concentrated autologous growth factor micro-injections to awaken dormant hair follicles.",
    treatmentId: "prp-hair-restoration",
    views: "1.9K",
    featured: true
  },
  {
    id: "reel-5",
    title: "Triple-Wavelength Painless Laser Hair Removal",
    category: "LASER CLINIC",
    tag: "ICE COOLING",
    videoUrl: "Video/clinic-reel.mp4",
    poster: "Photos/treatments/689495112_122215230932360851_8836278638910805268_n.jpg",
    description: "Advanced continuous ICE contact cooling for Fitzpatrick skin types III-VI.",
    treatmentId: "triple-laser-hair-removal",
    views: "2.1K",
    featured: true
  }
];

function getStoredReels() {
  const saved = localStorage.getItem('sapphire_reels_data');
  if (saved) {
    try {
      return JSON.parse(saved);
    } catch (e) {
      console.error("Failed to parse stored reels, using defaults", e);
    }
  }
  return DEFAULT_REELS_DATA;
}

function saveStoredReels(reels) {
  localStorage.setItem('sapphire_reels_data', JSON.stringify(reels));
}
