import { ANIM } from './animation-params';

export interface StarConfig {
  top: string;
  left: string;
  delay: string;
  width: string;
  height: string;
  color: string;
}

export const DAILY_STARS: StarConfig[] = [
  { top: '15%', left: '10%', delay: '0s',    width: '8px',  height: '8px',  color: '#fff' },
  { top: '25%', left: '85%', delay: '0.3s',  width: '6px',  height: '6px',  color: '#fff' },
  { top: '70%', left:  '8%', delay: '0.5s',  width: '5px',  height: '5px',  color: '#fff' },
  { top: '80%', left: '90%', delay: '0.7s',  width: '9px',  height: '9px',  color: '#a78bfa' },
  { top: '50%', left:  '5%', delay: '1.0s',  width: '4px',  height: '4px',  color: '#fff' },
  { top: '10%', left: '55%', delay: '1.2s',  width: '7px',  height: '7px',  color: '#f9a8d4' },
  { top: '90%', left: '45%', delay: '0.2s',  width: '6px',  height: '6px',  color: '#fff' },
  { top: '35%', left: '92%', delay: '1.5s',  width: '5px',  height: '5px',  color: '#fde68a' },
  { top: '60%', left: '78%', delay: '0.8s',  width: '6px',  height: '6px',  color: '#fff' },
  { top:  '5%', left: '30%', delay: '1.8s',  width: '10px', height: '10px', color: '#a78bfa' },
  { top: '45%', left: '15%', delay: '0.4s',  width: '4px',  height: '4px',  color: '#fff' },
  { top: '75%', left: '60%', delay: '2.0s',  width: '6px',  height: '6px',  color: '#fde68a' },
];

export const DAILY_TIMING = {
  mysterySway: `${ANIM.MYSTERY_SWAY}ms`,
  candyFloat:  `${ANIM.CANDY_FLOAT}ms`,
  glowPulse:   `${ANIM.GLOW_PULSE}ms`,
  beamSpin:    `${ANIM.BEAM_SPIN}ms`,
  starFly:     `${ANIM.STAR_FLY}ms`,
} as const;
