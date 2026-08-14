# 💎 Sapphire Luxury Aesthetics — Official Web Application
**Colombo 05, Sri Lanka | Medical Cosmetology & Wellness Sanctuary**

> Guided by **Dr. Indi (MBBS)** • Lead Aesthetic Physician & Clinical Director  
> Location: **No. 05, Thimbirigasyaya Place, Colombo 05, Sri Lanka**  
> Hotline / WhatsApp: **+94 77 714 3626** / **077 714 3626**  
> Official Facebook: [Sapphire Luxury Aesthetics](https://web.facebook.com/SapphireLuxuryAesthetics)

---

## 🌟 Project Architecture & Technologies

- **Frontend**: Modern HTML5, Tailwind CSS (Custom Sapphire & Cashmere Gold Luxury Theme), Lucide Icons, Swiper.js, AOS Smooth Animations, Canvas Confetti.
- **Zero Node.js Server Dependencies**: Can be opened directly in any browser (`index.html`) or hosted on any standard web hosting, cPanel, Apache, Nginx, or XAMPP.
- **Backend**: Lightweight PHP 7.4/8+ API handlers (`backend/booking.php`, `backend/inquiry.php`, `backend/newsletter.php`, `backend/treatments.php`) with JSON logging (`backend/data/`) and automated WhatsApp direct booking integration.

---

## 🚀 How to Run and Preview

### Option 1: Direct Browser Opening (Instant Preview)
Simply double-click `index.html` or open it with Google Chrome / MS Edge / Safari. All UI features, animations, filters, interactive quiz, before & after slider, and WhatsApp booking will work immediately!

### Option 2: Run with PHP / XAMPP / cPanel (Full Backend Integration)
1. If using **XAMPP / WAMP**: Copy this folder into your `htdocs/` or `www/` directory.
2. If using PHP CLI:
   ```bash
   php -S localhost:8000
   ```
3. Open `http://localhost:8000` in your web browser.

---

## 📂 Directory Structure

```
Sapphire luxury/
├── index.html                      # Main ultra-luxury responsive web page
├── assets/
│   ├── css/
│   │   └── style.css               # Luxury theme, glassmorphism, gold accents, slider CSS
│   └── js/
│       ├── app.js                  # Interactive logic: filters, booking wizard, quiz, slider
│       └── treatments-data.js      # Treatments catalog with local clinic photos
├── backend/
│   ├── booking.php                 # PHP appointment scheduling & WhatsApp generator
│   ├── inquiry.php                 # PHP contact inquiry handler
│   ├── newsletter.php              # PHP VIP club subscription handler
│   ├── treatments.php              # JSON API endpoint for treatment catalog
│   └── data/                       # Stores JSON database logs (auto-created)
├── Photos/                         # Clinic assets & treatment photography
│   ├── Logo.jpg                    # Official Sapphire Luxury Aesthetics Logo
│   └── treatments/                 # Dr. Indi, Acne, PRP, Laser & HydraFacial photos
└── README.md                       # Project documentation
```

---

## ✨ Features Included

1. **Top Luxury Announcement Bar**: Hotline `077 714 3626`, Colombo 05 address, working hours, and Facebook link.
2. **Glassmorphic Sticky Navbar**: Official Logo, smooth navigation, and "Book Appointment" luxury button.
3. **Editorial Hero Section**: Cinematic aesthetics headline, doctor credentials, FDA-approved badges, and quick treatment chips.
4. **Dr. Indi Spotlight**: Doctor profile, qualifications, clinical philosophy, and direct consultation booking.
5. **Interactive Treatments Explorer**: Category filter tabs (Skin, Hair, Laser, Anti-Aging, Body) and detailed modal showcases.
6. **Virtual Skin & Hair Advisor**: 2-step interactive quiz prescribing doctor-endorsed protocols.
7. **Interactive Before & After Slider**: Draggable transformation comparison.
8. **Multi-Step Booking System**: Seamless 3-step appointment scheduling with automated WhatsApp direct confirmation to `+94 77 714 3626`.
9. **Patient Testimonials**: Touch-enabled reviews carousel.
10. **Interactive FAQ Accordion**: Common questions answered for Sri Lankan skin types.
11. **Location & Contact**: Interactive Google map, clinic hours, parking info, and contact form.
12. **Floating WhatsApp Quick-Chat**: Instant one-tap WhatsApp concierge.
