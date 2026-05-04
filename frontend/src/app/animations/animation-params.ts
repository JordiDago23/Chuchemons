// All animation timing, easing, and color constants in one place.
// Change a value here to update it across every component that uses it.

export const ANIM = {
  // Easings
  BOUNCE:      'cubic-bezier(0.34, 1.56, 0.64, 1)',
  BOUNCE_SOFT: 'cubic-bezier(0.34, 1.4,  0.64, 1)',
  SPRING:      'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
  POP:         'cubic-bezier(0.175, 0.885, 0.32, 1.4)',
  EASE:        'ease',
  EASE_OUT:    'ease-out',
  LINEAR:      'linear',

  // General durations (ms)
  FAST:   150,
  NORMAL: 300,
  SLOW:   600,

  // Enter/leave animation durations (ms)
  CARD_IN:         600,
  POKEBALL_DROP:   700,
  POKEBALL_SPIN:   1200,
  POKEBALL_DELAY:  200,
  FADE_IN:         300,
  MODAL_SLIDE:     300,
  OVERLAY_IN:      450,
  POP_IN:          650,
  SLIDE_UP:        500,
  SLIDE_UP_DELAY:  350,
  SLIDE_UP_DELAY2: 550,
  CARD_BOUNCE_IN:  500,
  ITEM_POP_IN:     400,
  IMG_REVEAL:      600,

  // Continuous animation durations (ms) — used as CSS custom properties
  SPIN:        800,
  SHIMMER:     1400,
  PULSE:       1500,
  PULSE_DOT:   1500,
  DICE_ROLL:   400,
  FALL:        2000,

  // Evolution (ms)
  EVO_TOTAL:   3500,
  EVO_RING:    2400,
  EVO_SPARK:   1100,
  EVO_STAR:    3200,

  // Daily rewards continuous (ms)
  MYSTERY_SWAY: 3000,
  CANDY_FLOAT:  2000,
  GLOW_PULSE:   2000,
  BEAM_SPIN:    6000,
  STAR_FLY:     2500,
} as const;

/** Builds an Angular-animation timing string, e.g. "600ms 200ms ease" */
export const t = (ms: number, delayMs = 0, easing = 'ease'): string =>
  delayMs > 0 ? `${ms}ms ${delayMs}ms ${easing}` : `${ms}ms ${easing}`;

/** Converts ms to a CSS string, e.g. 1400 → "1400ms" */
export const ms = (val: number): string => `${val}ms`;
