import { Component, OnInit, ViewChild, ElementRef, AfterViewChecked } from '@angular/core';
import { ChatService, Message, Conversation } from '../../services/chat.service';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

interface User {
  id: number;
  nombre: string;
  email: string;
}

@Component({
  selector: 'app-chat',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './chat.component.html',
  styleUrls: ['./chat.component.css']
})
export class ChatComponent implements OnInit, AfterViewChecked {
  @ViewChild('messagesContainer') private messagesContainer!: ElementRef;

  conversations: Conversation[] = [];
  filteredConversations: Conversation[] = [];
  currentMessages: Message[] = [];
  user: User | null = null;
  selectedFriend: Conversation | null = null;
  selectedFriendId: number | null = null;
  currentUserId: number | null = null;
  messageText: string = '';
  searchQuery: string = '';

  constructor(private chatService: ChatService, private router: Router) {}

  ngOnInit(): void {
    this.loadCurrentUser();
    this.loadConversations();
    this.subscribeToMessages();
  }

  ngAfterViewChecked(): void {
    this.scrollToBottom();
  }

  private loadCurrentUser(): void {
    const userStr = localStorage.getItem('user');
    if (userStr) {
      this.user = JSON.parse(userStr);
      this.currentUserId = Number(this.user?.id) || null;
    }
  }

  logout(): void {
    localStorage.removeItem('access_token');
    localStorage.removeItem('user');
    this.router.navigate(['/login']);
  }

  loadConversations(): void {
    this.chatService.getConversations().subscribe({
      next: (response) => {
        this.conversations = response.data || [];
        this.filteredConversations = response.data || [];
      },
      error: () => {
        this.conversations = [];
        this.filteredConversations = [];
      }
    });
  }

  selectConversation(conversation: Conversation): void {
    this.selectedFriend = conversation;
    this.selectedFriendId = conversation.id;
    this.messageText = '';
    this.chatService.markAsRead(conversation.id).subscribe();
    this.loadMessages(conversation.id);
  }

  loadMessages(friendId: number): void {
    this.chatService.getConversation(friendId).subscribe({
      next: (response) => {
        this.currentMessages = response.data;
        this.chatService.setMessages(response.data);
      },
      error: () => {}
    });
  }

  sendMessage(): void {
    if (!this.messageText.trim() || !this.selectedFriendId) return;
    const message = this.messageText;
    this.messageText = '';
    this.chatService.sendMessage(this.selectedFriendId, message).subscribe({
      next: (response) => {
        const newMessage: Message = response.data;
        this.currentMessages.push(newMessage);
        this.chatService.setMessages(this.currentMessages);
      },
      error: () => {
        this.messageText = message;
      }
    });
  }

  filterConversations(): void {
    if (!this.searchQuery.trim()) {
      this.filteredConversations = this.conversations;
      return;
    }
    const query = this.searchQuery.toLowerCase();
    this.filteredConversations = this.conversations.filter(conv =>
      conv.last_message.toLowerCase().includes(query)
    );
  }

  private subscribeToMessages(): void {
    this.chatService.messages$.subscribe((messages) => {
      this.currentMessages = messages;
    });
  }

  isSentMessage(senderId: number): boolean {
    return Number(senderId) === Number(this.currentUserId);
  }

  formatTime(dateString: string | null): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  private scrollToBottom(): void {
    try {
      if (this.messagesContainer) {
        this.messagesContainer.nativeElement.scrollTop =
          this.messagesContainer.nativeElement.scrollHeight;
      }
    } catch {}
  }
}
