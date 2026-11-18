import Alpine from 'alpinejs';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { createIcons, Menu, X, Fuel, Ship, FileCheck, LogIn, MapPin, Phone, Mail, Clock, ArrowRight, Users, Anchor, ShieldCheck, ClipboardList } from 'lucide';

gsap.registerPlugin(ScrollTrigger);

window.Alpine = Alpine;
Alpine.start();

document.documentElement.classList.add('js');

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons: { Menu, X, Fuel, Ship, FileCheck, LogIn, MapPin, Phone, Mail, Clock, ArrowRight, Users, Anchor, ShieldCheck, ClipboardList } });

    if (reduceMotion) {
        document.querySelectorAll('[data-reveal]').forEach((el) => { el.style.opacity = 1; el.style.transform = 'none'; });
        return;
    }

    gsap.utils.toArray('[data-reveal]').forEach((el) => {
        gsap.to(el, {
            opacity: 1, y: 0, duration: 0.7, ease: 'power2.out',
            scrollTrigger: { trigger: el, start: 'top 90%', once: true },
        });
    });

    const hero = document.querySelector('[data-hero]');
    if (hero) {
        gsap.from(hero.querySelectorAll('[data-hero-item]'), { opacity: 0, y: 30, duration: 0.8, stagger: 0.12, ease: 'power3.out' });
    }
});
