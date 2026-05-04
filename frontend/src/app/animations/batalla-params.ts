import { ANIM } from './animation-params';

export interface ParticleConfig {
  left: string;
  delay: string;
  duration: string;
  color: string;
  width?: string;
  height?: string;
  borderRadius?: string;
}

export const BATTLE_PARTICLES: ParticleConfig[] = [
  { left:  '4%', delay: '0s',    duration: `${ANIM.FALL + 100}ms`, color: '#fde68a', width: '8px',  height: '8px' },
  { left: '12%', delay: '0.18s', duration: `${ANIM.FALL + 400}ms`, color: '#fb923c', width: '12px', height: '12px' },
  { left: '20%', delay: '0.32s', duration: `${ANIM.FALL - 100}ms`, color: '#a3e635', borderRadius: '2px' },
  { left: '28%', delay: '0.08s', duration: `${ANIM.FALL + 600}ms`, color: '#fde68a', width: '9px',  height: '9px' },
  { left: '36%', delay: '0.47s', duration: `${ANIM.FALL}ms`,       color: '#fff',    width: '7px',  height: '7px' },
  { left: '44%', delay: '0.22s', duration: `${ANIM.FALL + 300}ms`, color: '#fb923c', borderRadius: '2px' },
  { left: '52%', delay: '0.38s', duration: `${ANIM.FALL - 150}ms`, color: '#fde68a' },
  { left: '60%', delay: '0.05s', duration: `${ANIM.FALL + 550}ms`, color: '#a3e635', width: '11px', height: '11px' },
  { left: '68%', delay: '0.53s', duration: `${ANIM.FALL + 150}ms`, color: '#fff',    borderRadius: '2px' },
  { left: '76%', delay: '0.26s', duration: `${ANIM.FALL - 50}ms`,  color: '#fb923c', width: '8px',  height: '8px' },
  { left: '84%', delay: '0.41s', duration: `${ANIM.FALL + 700}ms`, color: '#fde68a', width: '12px', height: '12px' },
  { left: '92%', delay: '0.14s', duration: `${ANIM.FALL}ms`,       color: '#a3e635', borderRadius: '3px' },
];
