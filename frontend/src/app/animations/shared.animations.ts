import { trigger, transition, style, animate } from '@angular/animations';
import { ANIM, t } from './animation-params';

// Card slides up from below on page load
export const cardInAnim = trigger('cardIn', [
  transition(':enter', [
    style({ opacity: 0, transform: 'translateY(48px) scale(0.96)' }),
    animate(t(ANIM.CARD_IN, 0, ANIM.BOUNCE_SOFT), style({ opacity: 1, transform: 'translateY(0) scale(1)' })),
  ]),
]);

// Pokéball drops from above
export const pokeballDropAnim = trigger('pokeballDrop', [
  transition(':enter', [
    style({ opacity: 0, transform: 'translateY(-30px) scale(0.7)' }),
    animate(t(ANIM.POKEBALL_DROP, ANIM.POKEBALL_DELAY, ANIM.BOUNCE), style({ opacity: 1, transform: 'translateY(0) scale(1)' })),
  ]),
]);

// Pokéball SVG spins into place
export const pokeballSpinAnim = trigger('pokeballSpin', [
  transition(':enter', [
    style({ transform: 'rotate(-180deg)' }),
    animate(t(ANIM.POKEBALL_SPIN, ANIM.POKEBALL_DELAY, ANIM.EASE_OUT), style({ transform: 'rotate(0deg)' })),
  ]),
]);

// Alert banner slides down, slides up on leave
export const fadeInDownAnim = trigger('fadeInDown', [
  transition(':enter', [
    style({ opacity: 0, transform: 'translateY(-10px)' }),
    animate(t(ANIM.FADE_IN), style({ opacity: 1, transform: 'translateY(0)' })),
  ]),
  transition(':leave', [
    animate(t(ANIM.FADE_IN), style({ opacity: 0, transform: 'translateY(-10px)' })),
  ]),
]);

// Generic opacity fade for backdrops / wrappers
export const fadeInAnim = trigger('fadeIn', [
  transition(':enter', [
    style({ opacity: 0 }),
    animate(t(ANIM.FADE_IN, 0, ANIM.EASE_OUT), style({ opacity: 1 })),
  ]),
  transition(':leave', [
    animate(t(ANIM.FADE_IN, 0, ANIM.EASE_OUT), style({ opacity: 0 })),
  ]),
]);

// Modal/panel slides up and scales in
export const slideInAnim = trigger('slideIn', [
  transition(':enter', [
    style({ opacity: 0, transform: 'scale(0.9) translateY(-20px)' }),
    animate(t(ANIM.MODAL_SLIDE, 0, ANIM.EASE_OUT), style({ opacity: 1, transform: 'scale(1) translateY(0)' })),
  ]),
  transition(':leave', [
    animate(t(ANIM.MODAL_SLIDE, 0, ANIM.EASE_OUT), style({ opacity: 0, transform: 'scale(0.9) translateY(-20px)' })),
  ]),
]);

// Full-screen overlay fades in/scales in (result, reveal)
export const overlayInAnim = trigger('overlayIn', [
  transition(':enter', [
    style({ opacity: 0, transform: 'scale(1.04)' }),
    animate(t(ANIM.OVERLAY_IN), style({ opacity: 1, transform: 'scale(1)' })),
  ]),
  transition(':leave', [
    animate(t(ANIM.OVERLAY_IN), style({ opacity: 0 })),
  ]),
]);

// Emoji / large icon pops in with rotation
export const popInAnim = trigger('popIn', [
  transition(':enter', [
    style({ opacity: 0, transform: 'scale(0) rotate(-25deg)' }),
    animate(t(ANIM.POP_IN, 0, ANIM.POP), style({ opacity: 1, transform: 'scale(1) rotate(0deg)' })),
  ]),
]);

// Heading slides up on enter
export const slideUpAnim = trigger('slideUp', [
  transition(':enter', [
    style({ opacity: 0, transform: 'translateY(16px)' }),
    animate(t(ANIM.SLIDE_UP, ANIM.SLIDE_UP_DELAY), style({ opacity: 1, transform: 'translateY(0)' })),
  ]),
]);

// Sub-heading slides up slightly after heading
export const slideUpSubAnim = trigger('slideUpSub', [
  transition(':enter', [
    style({ opacity: 0, transform: 'translateY(16px)' }),
    animate(t(ANIM.SLIDE_UP, ANIM.SLIDE_UP_DELAY2), style({ opacity: 1, transform: 'translateY(0)' })),
  ]),
]);

// Reward card bounces in
export const cardBounceInAnim = trigger('cardBounceIn', [
  transition(':enter', [
    style({ opacity: 0, transform: 'scale(0.5) translateY(30px)' }),
    animate(t(ANIM.CARD_BOUNCE_IN, 0, ANIM.SPRING), style({ opacity: 1, transform: 'scale(1) translateY(0)' })),
  ]),
]);

// Small item card pops in
export const itemPopInAnim = trigger('itemPopIn', [
  transition(':enter', [
    style({ opacity: 0, transform: 'scale(0)' }),
    animate(t(ANIM.ITEM_POP_IN, 0, ANIM.SPRING), style({ opacity: 1, transform: 'scale(1)' })),
  ]),
]);

// Image bounces in with rotation
export const imgRevealAnim = trigger('imgReveal', [
  transition(':enter', [
    style({ opacity: 0, transform: 'scale(0.3) rotate(-15deg)' }),
    animate(t(ANIM.IMG_REVEAL, 0, ANIM.SPRING), style({ opacity: 1, transform: 'scale(1) rotate(0deg)' })),
  ]),
]);
