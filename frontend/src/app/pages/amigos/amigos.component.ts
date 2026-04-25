import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormControl, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, takeUntil } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { FriendUser, FriendsOverviewResponse, FriendsService } from '../../core/services/friends.service';
import { ConfirmDialogComponent } from '../../components/dialogs/confirm-dialog.component';
import { MainLayoutComponent } from '../../components/main-layout/main-layout.component';

@Component({
  selector: 'app-amigos',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterLink, ConfirmDialogComponent, MainLayoutComponent],
  templateUrl: './amigos.component.html',
  styleUrls: ['./amigos.component.css']
})
export class AmigosComponent implements OnInit, OnDestroy {
  user: any = null;

  friends: FriendUser[] = [];
  pendingReceived: FriendUser[] = [];
  pendingSent: FriendUser[] = [];
  searchResults: FriendUser[] = [];

  stats = { total: 0, online: 0, offline: 0 };

  loading = true;
  searching = false;
  showSearchPanel = false;
  searchControl = new FormControl('', { nonNullable: true });
  searchMessage = '';
  success = '';
  error = '';
  activeAction = '';
  showConfirmDialog = false;
  confirmTitle = '';
  confirmMessage = '';
  private confirmAction: (() => void) | null = null;
  private readonly destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private friendsService: FriendsService
  ) {}

  ngOnInit(): void {
    const cachedUser = this.auth.currentUser;
    if (cachedUser) {
      this.user = cachedUser;
    } else {
      this.auth.me().subscribe({
        next: (user) => {
          this.user = user;
        }
      });
    }

    this.friendsService.overview$
      .pipe(takeUntil(this.destroy$))
      .subscribe((response) => this.applyOverview(response));

    this.searchControl.valueChanges
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$)
      )
      .subscribe((value) => this.handleSearchTerm(value, false));

    this.loadOverview();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadOverview(silent = false): void {
    if (!silent) {
      this.loading = true;
    }

    this.friendsService.getOverview().subscribe({
      next: (response: FriendsOverviewResponse) => {
        this.friendsService.updateOverview(response);
        this.loading = false;
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo cargar la lista de amigos.';
        this.loading = false;
      }
    });
  }

  toggleSearchPanel(): void {
    this.showSearchPanel = !this.showSearchPanel;

    if (!this.showSearchPanel) {
      this.searchControl.setValue('', { emitEvent: false });
      this.searchResults = [];
      this.searchMessage = '';
    }
  }

  onSearch(): void {
    this.handleSearchTerm(this.searchControl.value, true);
  }

  private handleSearchTerm(rawValue: string, force: boolean): void {
    const term = rawValue.trim();
    this.searchResults = [];
    this.searchMessage = '';
    this.success = '';
    this.error = '';

    if (!this.showSearchPanel && !force) {
      return;
    }

    if (!term) {
      this.searchMessage = 'Introduce el ID o nombre del usuario que buscas.';
      return;
    }

    if (term.length < 3) {
      this.searchMessage = 'Escribe al menos 3 caracteres para buscar usuarios.';
      return;
    }

    this.searching = true;
    this.friendsService.searchUsers(term).subscribe({
      next: (response) => {
        this.searchResults = response.results ?? [];
        this.searchMessage = this.searchResults.length > 0 ? '' : (response.message ?? 'No se ha encontrado al usuario.');
        this.searching = false;
      },
      error: (err) => {
        this.searchMessage = err.error?.message ?? 'No se ha podido completar la búsqueda.';
        this.searching = false;
      }
    });
  }

  sendRequest(user: FriendUser): void {
    this.activeAction = `send-${user.id}`;
    this.success = '';
    this.error = '';

    this.friendsService.sendRequest(user.id).subscribe({
      next: (response) => {
        this.success = response.message;
        this.searchResults = this.searchResults.map((item) =>
          item.id === user.id
            ? {
                ...item,
                friendship_status: 'pending_sent',
                friendship_id: response.friendship.friendship_id ?? item.friendship_id ?? null,
                status: 'pending'
              }
            : item
        );
        this.activeAction = '';
        this.loadOverview(true);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo enviar la solicitud.';
        this.activeAction = '';
      }
    });
  }

  acceptRequest(request: FriendUser): void {
    if (!request.friendship_id) {
      return;
    }

    this.activeAction = `accept-${request.friendship_id}`;
    this.success = '';
    this.error = '';

    this.friendsService.acceptRequest(request.friendship_id).subscribe({
      next: (response) => {
        this.success = response.message;
        this.activeAction = '';
        this.loadOverview(true);
        this.refreshSearchResultStatus(request.id, 'friends', request.friendship_id ?? null, 'accepted');
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo aceptar la solicitud.';
        this.activeAction = '';
      }
    });
  }

  deleteRequest(request: FriendUser): void {
    if (!request.friendship_id) {
      return;
    }

    this.openConfirmDialog(
      'Eliminar solicitud',
      `¿Seguro que quieres eliminar la solicitud de ${request.player_id}?`,
      () => this.executeDeleteRequest(request)
    );
  }

  private executeDeleteRequest(request: FriendUser): void {
    if (!request.friendship_id) {
      return;
    }

    this.activeAction = `delete-${request.friendship_id}`;
    this.success = '';
    this.error = '';

    this.friendsService.deleteRequest(request.friendship_id).subscribe({
      next: (response) => {
        this.success = response.message;
        this.activeAction = '';
        this.loadOverview(true);
        this.refreshSearchResultStatus(request.id, 'none', null, null);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo eliminar la solicitud.';
        this.activeAction = '';
      }
    });
  }

  removeFriend(friend: FriendUser): void {
    this.openConfirmDialog(
      'Eliminar amigo',
      `¿Seguro que quieres eliminar a ${friend.player_id} de tu lista de amigos?`,
      () => this.executeRemoveFriend(friend)
    );
  }

  private executeRemoveFriend(friend: FriendUser): void {

    this.activeAction = `remove-${friend.id}`;
    this.success = '';
    this.error = '';

    this.friendsService.removeFriend(friend.id).subscribe({
      next: (response) => {
        this.success = response.message;
        this.activeAction = '';
        this.loadOverview(true);
        this.refreshSearchResultStatus(friend.id, 'none', null, null);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo eliminar al amigo.';
        this.activeAction = '';
      }
    });
  }

  avatarText(person: FriendUser): string {
    return person.nombre?.charAt(0)?.toUpperCase() || person.player_id?.replace('#', '').charAt(0)?.toUpperCase() || '?';
  }

  isActionLoading(prefix: string, id: number | null | undefined): boolean {
    return !!id && this.activeAction === `${prefix}-${id}`;
  }

  logout(): void {
    this.auth.logout();
  }

  onConfirmDialog(): void {
    const action = this.confirmAction;
    this.closeConfirmDialog();
    action?.();
  }

  onCancelDialog(): void {
    this.closeConfirmDialog();
  }

  get pendingRequestsCount(): number {
    return this.pendingReceived.length;
  }

  private applyOverview(response: FriendsOverviewResponse): void {
    this.friends = response.friends ?? [];
    this.pendingReceived = response.pending_received ?? [];
    this.pendingSent = response.pending_sent ?? [];
    this.stats = response.stats ?? { total: 0, online: 0, offline: 0 };
  }

  private openConfirmDialog(title: string, message: string, action: () => void): void {
    this.confirmTitle = title;
    this.confirmMessage = message;
    this.confirmAction = action;
    this.showConfirmDialog = true;
  }

  private closeConfirmDialog(): void {
    this.showConfirmDialog = false;
    this.confirmTitle = '';
    this.confirmMessage = '';
    this.confirmAction = null;
  }

  private refreshSearchResultStatus(
    userId: number,
    status: FriendUser['friendship_status'],
    friendshipId: number | null,
    requestStatus: FriendUser['status']
  ): void {
    this.searchResults = this.searchResults.map((item) =>
      item.id === userId
        ? {
            ...item,
            friendship_status: status,
            friendship_id: friendshipId,
            status: requestStatus
          }
        : item
    );
  }
}

