import {
  Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewChecked
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { Subject, BehaviorSubject, timer, EMPTY } from 'rxjs';
import { takeUntil, switchMap, tap, filter, catchError } from 'rxjs/operators';
import { ChatService, Message, Conversation } from '../../services/chat.service';
import { MainLayoutComponent } from '../main-layout/main-layout.component';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-chat',
  standalone: true,
  imports: [CommonModule, FormsModule, MainLayoutComponent],
  templateUrl: './chat.component.html',
  styleUrls: ['./chat.component.css']
})
export class ChatComponent implements OnInit, OnDestroy, AfterViewChecked {
  @ViewChild('messagesContainer') private messagesContainer!: ElementRef;

  user: any = null;
  conversations: Conversation[] = [];
  filteredConversations: Conversation[] = [];
  currentMessages: Message[] = [];
  selectedFriend: Conversation | null = null;
  selectedFriendId: number | null = null;
  currentUserId: number | null = null;
  messageText = '';
  searchQuery = '';
  sending = false;

  private shouldScrollToBottom = false;
  private destroy$ = new Subject<void>();
  private lastMsgId = 0; // rastrea el ID del último mensaje conocido

  // Emite el ID del amigo activo; null = ninguna conversación abierta
  private activeFriend$ = new BehaviorSubject<number | null>(null);

  constructor(
    private chatService: ChatService,
    private auth: AuthService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.loadCurrentUser();
    this.startConversationsStream();
    this.startMessagesStream();
  }

  ngAfterViewChecked(): void {
    if (this.shouldScrollToBottom) {
      this.scrollToBottom();
      this.shouldScrollToBottom = false;
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ── Usuario ──────────────────────────────────────────────────────

  private loadCurrentUser(): void {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      this.currentUserId = Number(cached.id) || null;
    } else {
      this.auth.me().pipe(takeUntil(this.destroy$)).subscribe({
        next: (user) => {
          this.user = user;
          this.currentUserId = Number(user?.id) || null;
        }
      });
    }
  }

  logout(): void {
    this.auth.logout();
  }

  // ── Stream 1: Lista de conversaciones (siempre activo) ───────────
  // Refresca cada 4 s → mantiene badges de no leídos al día
  // aunque el usuario esté en otra conversación o sin ninguna abierta

  private startConversationsStream(): void {
    const targetFriendId = Number(this.route.snapshot.queryParamMap.get('friendId')) || null;
    let autoOpened = false;

    timer(0, 4000).pipe(
      takeUntil(this.destroy$),
      switchMap(() => this.chatService.getConversations().pipe(
        catchError(() => EMPTY)
      ))
    ).subscribe(res => {
      this.conversations = res.data || [];
      this.applySearch();

      // Mantiene la referencia del amigo activo sincronizada
      if (this.selectedFriend) {
        const updated = this.conversations.find(c => c.id === this.selectedFriendId);
        if (updated) this.selectedFriend = updated;
      }

      // Abre automáticamente la conversación indicada por queryParam (solo la primera vez)
      if (targetFriendId && !autoOpened) {
        const target = this.conversations.find(c => c.id === targetFriendId);
        if (target) {
          autoOpened = true;
          this.selectConversation(target);
        }
      }
    });
  }

  // ── Stream 2: Mensajes del amigo activo ──────────────────────────
  // switchMap cancela el polling anterior automáticamente al cambiar
  // de conversación o al poner null (cerrar conversación)

  private startMessagesStream(): void {
    this.activeFriend$.pipe(
      takeUntil(this.destroy$),
      switchMap(friendId => {
        if (!friendId) return EMPTY;

        this.lastMsgId = 0; // resetea al cambiar de conversación

        // Carga inicial del historial completo
        return this.chatService.getConversation(friendId).pipe(
          tap(res => {
            this.currentMessages = res.data;
            this.lastMsgId = this.getLastId(res.data);
            this.shouldScrollToBottom = true;
            this.chatService.markAsRead(friendId).subscribe();
          }),
          // switchMap a polling de mensajes nuevos cada 1.5 s
          switchMap(() =>
            timer(1500, 1500).pipe(
              switchMap(() =>
                this.chatService.getNewMessages(friendId, this.lastMsgId).pipe(
                  catchError(() => EMPTY)
                )
              ),
              filter(res => res.data.length > 0),
              tap(res => {
                this.currentMessages = [...this.currentMessages, ...res.data];
                this.lastMsgId = this.getLastId(this.currentMessages);
                this.shouldScrollToBottom = true;
                this.chatService.markAsRead(friendId).subscribe();
              })
            )
          )
        );
      })
    ).subscribe();
  }

  // ── Acciones del usuario ─────────────────────────────────────────

  selectConversation(conversation: Conversation): void {
    this.selectedFriend = conversation;
    this.selectedFriendId = conversation.id;
    this.messageText = '';
    this.currentMessages = [];
    // Emitir nuevo ID cancela el polling anterior y arranca el nuevo
    this.activeFriend$.next(conversation.id);
  }

  sendMessage(): void {
    if (!this.messageText.trim() || !this.selectedFriendId || this.sending) return;
    const text = this.messageText;
    this.messageText = '';
    this.sending = true;

    this.chatService.sendMessage(this.selectedFriendId, text).subscribe({
      next: (response) => {
        const sent = response.data as Message;
        this.currentMessages = [...this.currentMessages, sent];
        // Actualiza lastMsgId para que el próximo poll no vuelva a pedir este mensaje
        this.lastMsgId = Math.max(this.lastMsgId, sent.id ?? 0);
        this.shouldScrollToBottom = true;
        this.sending = false;
      },
      error: () => {
        this.messageText = text;
        this.sending = false;
      }
    });
  }

  filterConversations(): void {
    this.applySearch();
  }

  // ── Helpers ──────────────────────────────────────────────────────

  private applySearch(): void {
    if (!this.searchQuery.trim()) {
      this.filteredConversations = [...this.conversations];
      return;
    }
    const q = this.searchQuery.toLowerCase();
    this.filteredConversations = this.conversations.filter(c =>
      c.friend_name?.toLowerCase().includes(q) ||
      (c.last_message ?? '').toLowerCase().includes(q)
    );
  }

  private getLastId(messages: Message[]): number {
    if (!messages.length) return 0;
    return Math.max(...messages.map(m => m.id ?? 0));
  }

  isSentMessage(senderId: number): boolean {
    return Number(senderId) === Number(this.currentUserId);
  }

  formatTime(dateString: string | null): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - date.getTime()) / 86400000);

    if (diffDays === 0) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (diffDays === 1) return 'Ayer';
    if (diffDays < 7)  return date.toLocaleDateString([], { weekday: 'short' });
    return date.toLocaleDateString([], { day: '2-digit', month: '2-digit' });
  }

  private scrollToBottom(): void {
    try {
      const el = this.messagesContainer?.nativeElement;
      if (el) el.scrollTop = el.scrollHeight;
    } catch {}
  }
}
