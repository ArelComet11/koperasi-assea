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

    initOrgCharts();
});

function initOrgCharts() {
    const charts = document.querySelectorAll('[data-org-chart]');
    if (!charts.length) return;

    const svgNS = 'http://www.w3.org/2000/svg';
    const drawPath = (g, d, withArrow = false) => {
        const path = document.createElementNS(svgNS, 'path');
        path.setAttribute('d', d);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', '#075B37');
        path.setAttribute('stroke-width', '2.4');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        if (withArrow) path.setAttribute('marker-end', 'url(#so-arrow-head)');
        g.appendChild(path);
    };

    const renderChart = (chart) => {
        if (chart.offsetParent === null) return;
        const g = chart.querySelector('[data-org-lines]');
        if (!g) return;
        g.innerHTML = '';

        const base = chart.getBoundingClientRect();
        const box = (el) => {
            const r = el.getBoundingClientRect();
            return {
                left: r.left - base.left,
                right: r.right - base.left,
                top: r.top - base.top,
                bottom: r.bottom - base.top,
                cx: r.left - base.left + r.width / 2,
                cy: r.top - base.top + r.height / 2,
            };
        };

        const nodesByLevel = (lvl) =>
            Array.from(chart.querySelectorAll(`[data-org-node][data-node-level="${lvl}"]`)).map((el) => ({
                el,
                ring: box(el.querySelector('[data-org-ring]') || el),
                pill: box(el.querySelector('[data-org-pill]') || el),
                full: box(el),
            }));

        const l1 = nodesByLevel(1);
        const l2 = nodesByLevel(2);
        const l3 = nodesByLevel(3);
        const l4 = nodesByLevel(4);
        const l2El = chart.querySelector('[data-org-level="2"]');
        const isFlankL2 = l2El?.dataset.orgFlank === 'true';

        // 1. Garis utama dari Level 1 turun ke Level 2 & Level 3
        const spineX = l1.length === 1 ? l1[0].pill.cx : base.width / 2;
        const spineStartY = l1.length === 1 ? l1[0].pill.bottom + 2 : 0;

        let spineEndY = spineStartY;
        if (l3.length > 0) {
            const l3Top = Math.min(...l3.map((n) => n.ring.top));
            const l2Bottom = l2.length > 0 ? Math.max(...l2.map((n) => n.pill.bottom)) : spineStartY;
            const barY = Math.round(Math.max(spineStartY + 26, (l2Bottom + l3Top) / 2));
            spineEndY = barY;

            if (l1.length === 1) {
                drawPath(g, `M ${spineX} ${spineStartY} V ${barY}`, false);
            } else if (l1.length > 1) {
                const l1Bot = Math.max(...l1.map((n) => n.pill.bottom)) + 2;
                const mid1 = l1Bot + 16;
                l1.forEach((n) => drawPath(g, `M ${n.pill.cx} ${n.pill.bottom + 2} V ${mid1}`, false));
                drawPath(g, `M ${l1[0].pill.cx} ${mid1} H ${l1[l1.length - 1].pill.cx}`, false);
                drawPath(g, `M ${spineX} ${mid1} V ${barY}`, false);
            }

            if (l3.length === 1) {
                drawPath(g, `M ${spineX} ${barY} H ${l3[0].ring.cx} V ${l3[0].ring.top - 6}`, true);
            } else {
                const minX = Math.min(...l3.map((n) => n.ring.cx));
                const maxX = Math.max(...l3.map((n) => n.ring.cx));
                drawPath(g, `M ${minX} ${barY} H ${maxX}`, false);
                l3.forEach((n) => {
                    drawPath(g, `M ${n.ring.cx} ${barY} V ${n.ring.top - 6}`, true);
                });
            }
        }

        // 2. Cabang horizontal dari garis utama ke Level 2 (kiri & kanan dengan panah)
        if (l2.length > 0) {
            if (isFlankL2 && l2.length === 2 && l1.length === 1) {
                const branchY = Math.round(Math.max(spineStartY + 14, (spineStartY + l2[0].pill.top) / 2));
                if (spineEndY < branchY) {
                    drawPath(g, `M ${spineX} ${spineStartY} V ${branchY}`, false);
                }
                const leftTargetX = l2[0].ring.right + 12;
                const rightTargetX = l2[1].ring.left - 12;
                if (leftTargetX < spineX - 10) {
                    drawPath(g, `M ${spineX} ${branchY} H ${leftTargetX}`, true);
                }
                if (rightTargetX > spineX + 10) {
                    drawPath(g, `M ${spineX} ${branchY} H ${rightTargetX}`, true);
                }
            } else if (l1.length > 0) {
                const l2Top = Math.min(...l2.map((n) => n.ring.top));
                const bar2Y = Math.round((spineStartY + l2Top) / 2);
                drawPath(g, `M ${spineX} ${spineStartY} V ${bar2Y}`, false);
                const minX = Math.min(...l2.map((n) => n.ring.cx));
                const maxX = Math.max(...l2.map((n) => n.ring.cx));
                if (l2.length > 1) drawPath(g, `M ${minX} ${bar2Y} H ${maxX}`, false);
                l2.forEach((n) => drawPath(g, `M ${n.ring.cx} ${bar2Y} V ${n.ring.top - 6}`, true));
            }
        }

        // 3. Penghubung Level 3 -> Level 4 (3 manajer -> 5 seksi: [2, 1, 2], atau distribusi merata dinamis)
        if (l3.length > 0 && l4.length > 0) {
            const groups = Array.from({ length: l3.length }, () => []);
            if (l3.length === 3 && l4.length === 5) {
                groups[0].push(l4[0], l4[1]);
                groups[1].push(l4[2]);
                groups[2].push(l4[3], l4[4]);
            } else {
                l4.forEach((child, idx) => {
                    const parentIdx = Math.min(l3.length - 1, Math.floor((idx * l3.length) / l4.length));
                    groups[parentIdx].push(child);
                });
            }

            groups.forEach((children, pIdx) => {
                if (!children.length) return;
                const parent = l3[pIdx];
                const startY = parent.pill.bottom + 2;
                const childTop = Math.min(...children.map((c) => c.ring.top));
                const midY = Math.round((startY + childTop) / 2);

                if (children.length === 1) {
                    const c = children[0];
                    if (Math.abs(parent.pill.cx - c.ring.cx) < 12) {
                        drawPath(g, `M ${parent.pill.cx} ${startY} V ${c.ring.top - 6}`, true);
                    } else {
                        drawPath(g, `M ${parent.pill.cx} ${startY} V ${midY} H ${c.ring.cx} V ${c.ring.top - 6}`, true);
                    }
                } else {
                    const minX = Math.min(...children.map((c) => c.ring.cx));
                    const maxX = Math.max(...children.map((c) => c.ring.cx));
                    drawPath(g, `M ${parent.pill.cx} ${startY} V ${midY}`, false);
                    drawPath(g, `M ${Math.min(minX, parent.pill.cx)} ${midY} H ${Math.max(maxX, parent.pill.cx)}`, false);
                    children.forEach((c) => {
                        drawPath(g, `M ${c.ring.cx} ${midY} V ${c.ring.top - 6}`, true);
                    });
                }
            });
        }
    };

    const renderAll = () => charts.forEach(renderChart);
    const ro = new ResizeObserver(renderAll);
    charts.forEach((c) => ro.observe(c));
    window.addEventListener('resize', renderAll);
    document.fonts?.ready.then(renderAll);
    requestAnimationFrame(renderAll);
}
