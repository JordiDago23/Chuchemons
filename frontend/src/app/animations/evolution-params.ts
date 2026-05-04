import { ANIM } from './animation-params';

export interface RingParam  { delay: string; color: string; }
export interface SparkParam { angle: string; color: string; delay: string; }
export interface StarParam  { top?: string; left?: string; bottom?: string; right?: string; delay: string; size: string; }

export const EVO_RINGS: RingParam[] = [
  { delay: '0.05s', color: 'rgba(255, 215, 0, 0.85)' },
  { delay: '0.22s', color: 'rgba(255, 140, 0, 0.75)' },
  { delay: '0.40s', color: 'rgba(255, 215, 0, 0.85)' },
  { delay: '0.58s', color: 'rgba(255, 140, 0, 0.75)' },
  { delay: '0.76s', color: 'rgba(255, 215, 0, 0.85)' },
];

export const EVO_SPARKS: SparkParam[] = [
  { angle: '0deg',   color: '#ffd700', delay: '0.08s' },
  { angle: '30deg',  color: '#ff9500', delay: '0.12s' },
  { angle: '60deg',  color: '#ffffff', delay: '0.05s' },
  { angle: '90deg',  color: '#ffd700', delay: '0.14s' },
  { angle: '120deg', color: '#ff9500', delay: '0.02s' },
  { angle: '150deg', color: '#ffd700', delay: '0.10s' },
  { angle: '180deg', color: '#ffffff', delay: '0.07s' },
  { angle: '210deg', color: '#ffd700', delay: '0.16s' },
  { angle: '240deg', color: '#ff9500', delay: '0.04s' },
  { angle: '270deg', color: '#ffd700', delay: '0.13s' },
  { angle: '300deg', color: '#ffffff', delay: '0.09s' },
  { angle: '330deg', color: '#ff9500', delay: '0.06s' },
];

export const EVO_STARS: StarParam[] = [
  { top: '18%',    left: '22%',  delay: '0.30s', size: '1.1rem' },
  { top: '14%',    right: '24%', delay: '0.50s', size: '1.6rem' },
  { bottom: '22%', left: '18%',  delay: '0.40s', size: '1.3rem' },
  { top: '28%',    left: '48%',  delay: '0.60s', size: '1.0rem' },
  { bottom: '18%', right: '21%', delay: '0.35s', size: '1.5rem' },
  { top: '42%',    right: '17%', delay: '0.55s', size: '1.2rem' },
];

export const EVO_TIMING = {
  totalDuration: `${ANIM.EVO_TOTAL / 1000}s`,
  ringDuration:  `${ANIM.EVO_RING  / 1000}s`,
  sparkDuration: `${ANIM.EVO_SPARK / 1000}s`,
  starDuration:  `${ANIM.EVO_STAR  / 1000}s`,
  sparkEasing:   'cubic-bezier(0.22, 0.61, 0.36, 1)',
} as const;
