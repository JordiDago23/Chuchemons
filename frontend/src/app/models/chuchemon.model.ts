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
  attack: number;
  defense: number;
  speed: number;
  count?: number;
  captured?: boolean;
  active_infections?: ChuchemonInfection[];
  has_active_infections?: boolean;
  cannot_eat?: boolean;
  cannot_eat_reason?: string | null;
}
