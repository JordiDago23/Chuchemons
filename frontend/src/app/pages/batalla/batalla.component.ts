import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { EMPTY, interval, Subject, Subscription } from 'rxjs';
import { switchMap, takeUntil } from 'rxjs/operators';
import {
  BattleDetailsResponse,
  BattleOverviewResponse,
  BattleRequestSummary,
  BattleRosterEntry,
  BattleService,
  BattleSummary,
  BattleUser,
} from '../../core/services/battle.service';
import { AuthService } from '../../core/services/auth.service';
import { MainLayoutComponent } from '../../components/main-layout/main-layout.component';

@Component({
  selector: 'app-batalla',
  standalone: true,
  imports: [CommonModule, MainLayoutComponent],
  templateUrl: './batalla.component.html',
  styleUrls: ['./batalla.component.css'],
})
export class BatallaComponent implements OnInit, OnDestroy {
  user: any = null;

  onlineFriends: BattleUser[] = [];
  pendingReceived: BattleRequestSummary[] = [];
  pendingSent: BattleRequestSummary[] = [];
  activeBattles: BattleSummary[] = [];
  recentBattles: BattleSummary[] = [];

  selectedBattle: BattleSummary | null = null;
  myRoster: BattleRosterEntry[] = [];
  opponentRoster: BattleRosterEntry[] = [];

  stats = {
    victories: 0,
    defeats: 0,
    total: 0,
    win_rate: 0,
    streak: 0,
  };

  loading = true;
  battleLoading = false;
  actionLoading = '';
  success = '';
  error = '';
  diceRolling = false;
  currentBattleId: number | null = null;
  showResultOverlay = false;
  resultAnimDone = false;
  readonly particles = [1,2,3,4,5,6,7,8,9,10,11,12];

  private readonly destroy$ = new Subject<void>();
  private pollingSubscription: Subscription | null = null;
  private overviewSub: Subscription | null = null;

  constructor(
    private auth: AuthService,
    private battleService: BattleService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit(): void {
    const cachedUser = this.auth.currentUser;
    if (cachedUser) {
      this.user = cachedUser;
    } else {
      this.auth.me().subscribe({
        next: (user) => {
          this.user = user;
        },
      });
    }

    this.loadOverview();

    this.route.paramMap.pipe(takeUntil(this.destroy$)).subscribe((params) => {
      const battleId = Number(params.get('battleId') || 0);

      if (battleId > 0) {
        this.currentBattleId = battleId;
        this.stopOverviewPolling();
        this.fetchBattle(battleId);
        return;
      }

      this.currentBattleId = null;
      this.selectedBattle = null;
      this.myRoster = [];
      this.opponentRoster = [];
      this.loadOverview(true);
      this.startOverviewPolling();
    });

    this.route.queryParamMap.pipe(takeUntil(this.destroy$)).subscribe((params) => {
      if (this.isCombatPage) {
        return;
      }

      const friendId = Number(params.get('friendId') || 0);
      if (friendId > 0) {
        this.quickChallenge(friendId);
      }
    });
  }

  ngOnDestroy(): void {
    this.stopPolling();
    this.stopOverviewPolling();
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadOverview(silent = false): void {
    if (!silent) {
      this.loading = true;
    }

    this.battleService.getOverview().subscribe({
      next: (response: BattleOverviewResponse) => {
        this.onlineFriends = response.online_friends ?? [];
        this.pendingReceived = response.pending_received ?? [];
        this.pendingSent = response.pending_sent ?? [];
        this.activeBattles = response.active_battles ?? [];
        this.recentBattles = response.recent_battles ?? [];
        this.stats = response.stats ?? this.stats;
        this.loading = false;
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo cargar la Arena de Batalla.';
        this.loading = false;
      },
    });
  }

  quickChallenge(friendId: number): void {
    if (!friendId || this.actionLoading === `challenge-${friendId}`) {
      return;
    }

    this.sendChallenge(friendId, true);
  }

  sendChallenge(friendId: number, silent = false): void {
    this.actionLoading = `challenge-${friendId}`;
    if (!silent) {
      this.success = '';
      this.error = '';
    }

    this.battleService.sendRequest(friendId).subscribe({
      next: (response) => {
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
        this.clearQueryParams();
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo enviar el desafío.';
        this.actionLoading = '';
      },
    });
  }

  acceptRequest(requestId: number): void {
    this.actionLoading = `accept-${requestId}`;
    this.success = '';
    this.error = '';

    this.battleService.acceptRequest(requestId).subscribe({
      next: (response) => {
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
        this.openBattle(response.battle_id);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo aceptar el desafío.';
        this.actionLoading = '';
      },
    });
  }

  deleteRequest(requestId: number): void {
    this.actionLoading = `delete-${requestId}`;
    this.success = '';
    this.error = '';

    this.battleService.deleteRequest(requestId).subscribe({
      next: (response) => {
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo eliminar la solicitud.';
        this.actionLoading = '';
      },
    });
  }

  openBattle(battleId: number): void {
    this.router.navigate(['/batalla', battleId]);
  }

  private fetchBattle(battleId: number): void {
    this.battleLoading = true;
    this.success = '';
    this.error = '';

    this.battleService.getBattle(battleId).subscribe({
      next: (response: BattleDetailsResponse) => {
        this.selectedBattle = response.battle;
        this.myRoster = response.my_roster ?? [];
        this.opponentRoster = response.opponent_roster ?? [];
        this.battleLoading = false;
        if (response.battle.status === 'completed') {
          this.triggerResultOverlay();
        }
        this.startPollingIfNeeded();
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo abrir la batalla.';
        this.battleLoading = false;
      },
    });
  }

  private startPollingIfNeeded(): void {
    this.stopPolling();
    const battle = this.selectedBattle;
    const userId = this.user?.id;
    if (!battle || !userId) return;

    const waitingForOpponentSelect = battle.status === 'pending_selection' && !!battle.my_selection;
    const inCombatRivalTurn        = battle.status === 'in_combat' && !battle.is_my_turn;
    const loserWaitingForClaim     = battle.status === 'completed' && !battle.winner_chuchemon_id && battle.winner_id !== userId;

    if (!waitingForOpponentSelect && !inCombatRivalTurn && !loserWaitingForClaim) return;

    this.pollingSubscription = interval(2000)
      .pipe(
        takeUntil(this.destroy$),
        switchMap(() =>
          this.currentBattleId ? this.battleService.getBattle(this.currentBattleId) : EMPTY
        )
      )
      .subscribe({
        next: (response: BattleDetailsResponse) => {
          if (response.battle.status === 'cancelled') {
            this.stopPolling();
            this.error = 'Tu rival abandonó la batalla.';
            setTimeout(() => this.router.navigate(['/batalla']), 2000);
            return;
          }

          this.selectedBattle = response.battle;
          this.myRoster = response.my_roster ?? this.myRoster;
          this.opponentRoster = response.opponent_roster ?? this.opponentRoster;

          // Si el combate pasó a ser mi turno, parar el polling (el jugador ahora actúa)
          if (response.battle.status === 'in_combat' && response.battle.is_my_turn) {
            this.stopPolling();
            return;
          }

          if (response.battle.status === 'completed') {
            this.triggerResultOverlay();
            const isLoser = response.battle.winner_id !== this.user?.id;
            const claimed = !!response.battle.winner_chuchemon_id;
            if (!isLoser || claimed) {
              this.stopPolling();
              this.loadOverview(true);
            }
          }
        },
      });
  }

  private triggerResultOverlay(): void {
    if (!this.showResultOverlay && this.hasResult) {
      this.showResultOverlay = true;
      setTimeout(() => { this.resultAnimDone = true; }, 2200);
    }
  }

  goBack(): void {
    this.stopPolling();
    this.showResultOverlay = false;
    this.resultAnimDone = false;
    this.router.navigate(['/batalla']);
    // overview polling se arrancará cuando el paramMap detecte battleId=null
  }

  private startOverviewPolling(): void {
    this.stopOverviewPolling();
    this.overviewSub = interval(5000)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        if (!this.isCombatPage) {
          this.loadOverview(true);
        }
      });
  }

  private stopOverviewPolling(): void {
    if (this.overviewSub) {
      this.overviewSub.unsubscribe();
      this.overviewSub = null;
    }
  }

  private stopPolling(): void {
    if (this.pollingSubscription) {
      this.pollingSubscription.unsubscribe();
      this.pollingSubscription = null;
    }
  }

  selectForBattle(chuchemonId: number): void {
    if (!this.selectedBattle) {
      return;
    }

    this.actionLoading = `select-${chuchemonId}`;
    this.success = '';
    this.error = '';

    this.battleService.selectChuchemon(this.selectedBattle.id, chuchemonId).subscribe({
      next: (response) => {
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
        if (this.currentBattleId) {
          this.fetchBattle(this.currentBattleId);
        }
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo enviar la selección.';
        this.actionLoading = '';
      },
    });
  }

  closeBattlePanel(): void {
    const status = this.selectedBattle?.status;
    if (!this.selectedBattle || (status !== 'pending_selection' && status !== 'in_combat')) {
      this.stopPolling();
      this.router.navigate(['/batalla']);
      return;
    }

    this.actionLoading = 'cancel';
    this.battleService.cancelBattle(this.selectedBattle.id).subscribe({
      next: () => {
        this.actionLoading = '';
        this.stopPolling();
        this.router.navigate(['/batalla']);
      },
      error: () => {
        // Si falla (ej. ya completada), salimos igualmente
        this.actionLoading = '';
        this.stopPolling();
        this.router.navigate(['/batalla']);
      }
    });
  }

  get isCombatPage(): boolean {
    return this.currentBattleId !== null;
  }

  isLoadingAction(prefix: string, id: number): boolean {
    return this.actionLoading === `${prefix}-${id}`;
  }

  get canSelect(): boolean {
    return !!this.selectedBattle
      && this.selectedBattle.status === 'pending_selection'
      && !this.selectedBattle.my_selection;
  }

  get hasResult(): boolean {
    return !!this.selectedBattle && this.selectedBattle.status === 'completed';
  }

  get canClaim(): boolean {
    return !!this.selectedBattle?.can_claim;
  }

  get isWaitingForClaim(): boolean {
    const b = this.selectedBattle;
    return !!b && b.status === 'completed' && !b.winner_chuchemon_id && !b.can_claim;
  }

  claimFromBattle(chuchemonId: number): void {
    if (!this.selectedBattle || !this.canClaim) {
      return;
    }

    this.actionLoading = `claim-${chuchemonId}`;
    this.success = '';
    this.error = '';

    this.battleService.claimChuchemon(this.selectedBattle.id, chuchemonId).subscribe({
      next: (response) => {
        this.actionLoading = '';
        this.selectedBattle = response.battle;
        this.stopPolling();
        this.loadOverview(true);
      },
      error: (err: any) => {
        this.error = err.error?.message ?? 'No se pudo reclamar el Xuxemon.';
        this.actionLoading = '';
      },
    });
  }

  rollDice(): void {
    if (!this.selectedBattle || this.diceRolling) return;
    this.diceRolling = true;
    this.error = '';

    this.battleService.rollDice(this.selectedBattle.id).subscribe({
      next: (response) => {
        this.selectedBattle = response.battle;
        this.diceRolling = false;
        if (!response.battle_over) {
          this.startPollingIfNeeded();
        } else {
          this.triggerResultOverlay();
          this.loadOverview(true);
        }
      },
      error: (err) => {
        this.error = err.error?.message ?? 'Error al tirar el dado.';
        this.diceRolling = false;
      }
    });
  }

  get isMyTurn(): boolean {
    return !!this.selectedBattle?.is_my_turn;
  }

  get isInCombat(): boolean {
    return this.selectedBattle?.status === 'in_combat';
  }

  get myHpPercent(): number {
    const b = this.selectedBattle;
    if (!b || b.my_current_hp == null) return 100;
    const max = this.myFighter?.max_hp ?? 100;
    return Math.max(0, Math.round((b.my_current_hp / max) * 100));
  }

  get opponentHpPercent(): number {
    const b = this.selectedBattle;
    if (!b || b.opponent_current_hp == null) return 100;
    const max = this.opponentFighter?.max_hp ?? 100;
    return Math.max(0, Math.round((b.opponent_current_hp / max) * 100));
  }

  get hasBothSelections(): boolean {
    return !!this.selectedBattle?.my_selection && !!this.selectedBattle?.opponent_selection;
  }

  get battlePhaseLabel(): string {
    if (!this.selectedBattle) return '';
    switch (this.selectedBattle.status) {
      case 'in_combat':  return this.isMyTurn ? '⚔️ Tu turno' : '⏳ Turno del rival';
      case 'completed':  return 'Resultado final';
      default:           return 'Esperando selección';
    }
  }

  get myFighter(): BattleRosterEntry | null {
    const id = this.selectedBattle?.my_selection;
    if (!id) {
      return null;
    }

    return this.myRoster.find((entry) => entry.id === id) ?? null;
  }

  get opponentFighter(): BattleRosterEntry | null {
    const id = this.selectedBattle?.opponent_selection;
    if (!id) {
      return null;
    }

    return this.opponentRoster.find((entry) => entry.id === id) ?? null;
  }

  get battleLogLines(): string[] {
    const battle = this.selectedBattle;
    if (!battle) {
      return [];
    }

    const lines: string[] = ['La batalla ha comenzado.'];

    if (!battle.my_selection) {
      lines.push('Selecciona tu Xuxemon para empezar el combate.');
      return lines;
    }

    if (!battle.opponent_selection) {
      lines.push('Tu selección está lista. Esperando al oponente.');
      return lines;
    }

    const myName = this.myFighter?.name ?? 'Tu Xuxemon';
    const oppName = this.opponentFighter?.name ?? 'Xuxemon rival';
    lines.push(`${myName} vs ${oppName}.`);

    if (battle.status === 'completed') {
      lines.push(this.resultText);
      lines.push(
        `Tu cálculo: ${this.myRoll() ?? '-'} + ${this.formatSigned(this.myTypeMod())} + ${this.formatSigned(this.mySizeMod())} = ${this.myTotal() ?? '-'}`
      );
      lines.push(
        `Rival: ${this.opponentRoll() ?? '-'} + ${this.formatSigned(this.opponentTypeMod())} + ${this.formatSigned(this.opponentSizeMod())} = ${this.opponentTotal() ?? '-'}`
      );
    }

    return lines;
  }

  get didIWin(): boolean {
    return !!this.selectedBattle && !!this.user && this.selectedBattle.winner_id === this.user.id;
  }

  get resultText(): string {
    if (!this.selectedBattle || !this.hasResult) {
      return '';
    }

    return this.didIWin
      ? 'Has ganado la batalla y robas el Xuxemon seleccionado del rival.'
      : 'Has perdido la batalla y el rival te roba el Xuxemon seleccionado.';
  }

  battleOutcomeLabel(battle: BattleSummary): string {
    if (!this.user || battle.status !== 'completed') {
      return 'Pendiente';
    }

    if (battle.winner_id === this.user.id) {
      return 'Victoria';
    }

    if (battle.loser_id === this.user.id) {
      return 'Derrota';
    }

    return 'Completada';
  }

  myRoll(): number | null {
    return this.battleMetric('rolls', 'me');
  }

  opponentRoll(): number | null {
    return this.battleMetric('rolls', 'opponent');
  }

  myTypeMod(): number | null {
    return this.battleMetric('type_modifiers', 'me');
  }

  opponentTypeMod(): number | null {
    return this.battleMetric('type_modifiers', 'opponent');
  }

  mySizeMod(): number | null {
    return this.battleMetric('size_modifiers', 'me');
  }

  opponentSizeMod(): number | null {
    return this.battleMetric('size_modifiers', 'opponent');
  }

  myTotal(): number | null {
    return this.battleMetric('final_scores', 'me');
  }

  opponentTotal(): number | null {
    return this.battleMetric('final_scores', 'opponent');
  }

  formatSigned(value: number | null): string {
    if (value === null || Number.isNaN(value)) {
      return '-';
    }

    return value >= 0 ? `+${value}` : `${value}`;
  }

  sizeLabel(mida: string | null | undefined): string {
    const map: Record<string, string> = { Petit: 'Pequeño', 'Mitjà': 'Mediano', Mitja: 'Mediano', Gran: 'Grande' };
    return mida ? (map[mida] ?? mida) : '-';
  }

  imageFor(entry: BattleRosterEntry | null): string {
    if (!entry?.image) {
      return 'https://placehold.co/760x340?text=Xuxemon';
    }

    return 'http://localhost:8000/chuchemons/' + entry.image;
  }

  private battleMetric(
    metric: 'rolls' | 'type_modifiers' | 'size_modifiers' | 'final_scores',
    side: 'me' | 'opponent'
  ): number | null {
    const battle = this.selectedBattle;
    const payload = battle?.result_payload;
    const userId = this.user?.id;

    if (!battle || !payload || !userId) {
      return null;
    }

    const myRole = this.roleForUser(battle, userId);
    if (!myRole) {
      return null;
    }

    const role = side === 'me' ? myRole : myRole === 'challenger' ? 'challenged' : 'challenger';
    return payload[metric]?.[role] ?? null;
  }

  private roleForUser(battle: BattleSummary, userId: number): 'challenger' | 'challenged' | null {
    if (battle.challenger_id === userId) {
      return 'challenger';
    }

    if (battle.challenged_id === userId) {
      return 'challenged';
    }

    return null;
  }

  trackById(_: number, item: { id: number }): number {
    return item.id;
  }

  logout(): void {
    this.auth.logout();
  }

  private clearQueryParams(): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {},
      replaceUrl: true,
    });
  }
}

