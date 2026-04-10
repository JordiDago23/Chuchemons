export interface ChuchemonInfection {
  id: number;
  name: string;
  type: string;
  severity: number;
  infection_percentage: number;
}

export interface Chuchemon {
  id: number;
  name: string;
  element: 'Terra' | 'Tierra' | 'Aire' | 'Aigua' | 'Agua';
  image: string;
  mida?: 'Petit' | 'Mitjà' | 'Gran';
  current_mida?: 'Petit' | 'Mitjà' | 'Gran';
  attack: number;
  defense: number;
  speed: number;
  effective_attack?: number;
  effective_defense?: number;
  effective_speed?: number;
  attack_boost?: number;
  defense_boost?: number;
  count?: number;
  captured?: boolean;
  level?: number;
  experience?: number;
  experience_for_next_level?: number;
  experience_progress?: number;
  current_hp?: number;
  max_hp?: number;
  hp_percent?: number;
  active_infections?: ChuchemonInfection[];
  has_active_infections?: boolean;
  cannot_eat?: boolean;
  cannot_eat_reason?: string | null;
}
