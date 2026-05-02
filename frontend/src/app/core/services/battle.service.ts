import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface BattleUser {
  id: number;
  nombre: string;
  apellidos: string;
  display_name: string;
  player_id: string;
  bio?: string | null;
  is_online: boolean;
  last_seen_at?: string | null;
}

export interface BattleRosterEntry {
  id: number;
  name: string;
  element: string;
  image?: string | null;
  attack: number;
  defense: number;
  speed: number;
  count: number;
  level: number;
  current_mida: string;
  current_hp: number;
  max_hp: number;
  hp_percent: number;
}

export interface BattleResultPayload {
  rolls: {
    challenger: number;
    challenged: number;
  };
  type_modifiers: {
    challenger: number;
    challenged: number;
  };
  size_modifiers: {
    challenger: number;
    challenged: number;
  };
  final_scores: {
    challenger: number;
    challenged: number;
  };
  stolen: boolean;
}

export interface CombatLogEntry {
  turn: number;
  attacker_id: number;
  attacker_name: string;
  defender_name: string;
  roll: number;
  size_mod: number;
  type_mod: number;
  attack: number;
  attack_total: number;
  defense: number;
  damage: number;
  hp_before: number;
  hp_after: number;
}

export interface BattleSummary {
  id: number;
  status: 'pending_selection' | 'in_combat' | 'completed' | 'cancelled';
  challenger_id: number;
  challenged_id: number;
  created_at?: string | null;
  resolved_at?: string | null;
  opponent: BattleUser | null;
  my_selection: number | null;
  opponent_selection: number | null;
  winner_id: number | null;
  loser_id: number | null;
  winner_chuchemon_id: number | null;
  loser_chuchemon_id: number | null;
  can_claim?: boolean;
  result_payload?: BattleResultPayload | null;
  // Combate por turnos
  current_turn_id: number | null;
  is_my_turn: boolean;
  my_current_hp: number | null;
  opponent_current_hp: number | null;
  last_roll: CombatLogEntry | null;
  combat_log: CombatLogEntry[];
}

export interface BattleRequestSummary {
  id: number;
  created_at?: string | null;
  user: BattleUser;
}

export interface BattleOverviewResponse {
  online_friends: BattleUser[];
  pending_received: BattleRequestSummary[];
  pending_sent: BattleRequestSummary[];
  active_battles: BattleSummary[];
  recent_battles: BattleSummary[];
  stats: {
    victories: number;
    defeats: number;
    total: number;
    win_rate: number;
    streak: number;
  };
}

export interface BattleDetailsResponse {
  battle: BattleSummary;
  my_roster: BattleRosterEntry[];
  opponent_roster: BattleRosterEntry[];
}

@Injectable({ providedIn: 'root' })
export class BattleService {
  private readonly apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  getOverview(): Observable<BattleOverviewResponse> {
    return this.http.get<BattleOverviewResponse>(`${this.apiUrl}/battle`);
  }

  sendRequest(friendId: number): Observable<{ message: string; request: { id: number; friend_id: number; status: string } }> {
    return this.http.post<{ message: string; request: { id: number; friend_id: number; status: string } }>(`${this.apiUrl}/battle/request`, {
      friend_id: friendId,
    });
  }

  acceptRequest(requestId: number): Observable<{ message: string; battle_id: number }> {
    return this.http.post<{ message: string; battle_id: number }>(`${this.apiUrl}/battle/requests/${requestId}/accept`, {});
  }

  deleteRequest(requestId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/battle/requests/${requestId}`);
  }

  getBattle(battleId: number): Observable<BattleDetailsResponse> {
    return this.http.get<BattleDetailsResponse>(`${this.apiUrl}/battle/${battleId}`);
  }

  selectChuchemon(battleId: number, chuchemonId: number): Observable<{ message: string; battle: BattleSummary; resolved: boolean }> {
    return this.http.post<{ message: string; battle: BattleSummary; resolved: boolean }>(`${this.apiUrl}/battle/${battleId}/select`, {
      chuchemon_id: chuchemonId,
    });
  }

  claimChuchemon(battleId: number, chuchemonId: number): Observable<{ message: string; battle: BattleSummary }> {
    return this.http.post<{ message: string; battle: BattleSummary }>(`${this.apiUrl}/battle/${battleId}/claim`, {
      chuchemon_id: chuchemonId,
    });
  }

  rollDice(battleId: number): Observable<{ message: string; battle: BattleSummary; last_roll: CombatLogEntry; battle_over: boolean }> {
    return this.http.post<{ message: string; battle: BattleSummary; last_roll: CombatLogEntry; battle_over: boolean }>(
      `${this.apiUrl}/battle/${battleId}/roll`, {}
    );
  }

  cancelBattle(battleId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/battle/${battleId}`);
  }
}
