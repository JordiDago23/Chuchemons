import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface EvolutionInfo {
  chuchemon_id: number;
  name: string;
  current_mida: 'Petit' | 'Mitjà' | 'Gran';
  next_mida?: 'Petit' | 'Mitjà' | 'Gran';
  can_evolve: boolean;
  evolution_count: number;
}

export interface EvolveResponse {
  message: string;
  chuchemon: {
    id: number;
    name: string;
    element: string;
    mida: string;
    image?: string;
    current_mida: string;
    evolution_count: number;
  };
}

@Injectable({ providedIn: 'root' })
export class EvolutionService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  evolveChuchemon(chuchemonId: number): Observable<EvolveResponse> {
    return this.http.post<EvolveResponse>(
      `${this.apiUrl}/user/chuchemons/${chuchemonId}/evolve`,
      {}
    );
  }

  getEvolutionInfo(chuchemonId: number): Observable<EvolutionInfo> {
    return this.http.get<EvolutionInfo>(
      `${this.apiUrl}/user/chuchemons/${chuchemonId}/evolution`
    );
  }
}
