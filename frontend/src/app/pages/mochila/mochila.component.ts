import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

// ── Models ────────────────────────────────────────────────────────────────────
export interface ItemBase {
  id: number;
  name: string;
  description: string;
  imageUrl: string;
  tag: string;  // 'Captura' | 'Mejora' | etc.
}

/** Apilable (Xux): max 5 per slot, ceil(qty/5) spaces */
export interface XuxItem extends ItemBase {
  type: 'xux';
  quantity: number;
}

/** No apilable (Vacuna): 1 space each unit */
export interface VacunaItem extends ItemBase {
  type: 'vacuna';
  quantity: number; // each unit = 1 space
}

export type Item = XuxItem | VacunaItem;

// ── Component ─────────────────────────────────────────────────────────────────
@Component({
  selector: 'app-mochila',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './mochila.component.html',
  styleUrls: ['./mochila.component.css']
})
export class MochilaComponent implements OnInit {
  user: any = null;
  loading = true;

  activeTab: 'objetos' | 'vacunas' = 'objetos';

  readonly MAX_SPACES = 20;
  readonly MAX_STACK = 5;

  // ── 3 tipos de Xux (Apilables) ────────────────────────────────────────────
  xuxItems: XuxItem[] = [
    {
      id: 1,
      type: 'xux',
      name: 'Gominola Roja',
      description: 'Una dulce gominola que restaura energía al Chuchemon.',
      imageUrl: 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Pokeball_-_Pok%C3%A9mon_symbol.svg/512px-Pokeball_-_Pok%C3%A9mon_symbol.svg.png',
      tag: 'Curación',
      quantity: 12,
    },
    {
      id: 2,
      type: 'xux',
      name: 'Gominola Azul',
      description: 'Una fresca gominola que potencia los ataques del Chuchemon.',
      imageUrl: 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Pokeball_-_Pok%C3%A9mon_symbol.svg/512px-Pokeball_-_Pok%C3%A9mon_symbol.svg.png',
      tag: 'Ataque',
      quantity: 7,
    },
    {
      id: 3,
      type: 'xux',
      name: 'Caramelo Raro',
      description: 'Caramelos para mejorar a tu Chuchemon.',
      imageUrl: 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Pokeball_-_Pok%C3%A9mon_symbol.svg/512px-Pokeball_-_Pok%C3%A9mon_symbol.svg.png',
      tag: 'Mejora',
      quantity: 3,
    },
  ];

  // ── Vacunes (No apilables) ─────────────────────────────────────────────────
  vacunaItems: VacunaItem[] = [
    {
      id: 4,
      type: 'vacuna',
      name: 'Xocolatina',
      description: 'En usar-ho en un Xuxemon treu "Bajón de azúcar".',
      imageUrl: '',
      tag: 'Estat',
      quantity: 3,
    },
    {
      id: 5,
      type: 'vacuna',
      name: 'Xal de fruites',
      description: 'En usar-ho en un Xuxemon treu "Atracón".',
      imageUrl: '',
      tag: 'Estat',
      quantity: 2,
    },
    {
      id: 6,
      type: 'vacuna',
      name: 'Inxulina',
      description: 'Cura totes les malalties del Xuxemon.',
      imageUrl: '',
      tag: 'Cura',
      quantity: 1,
    },
  ];

  constructor(private auth: AuthService) {}

  ngOnInit() {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      this.loading = false;
      return;
    }
    this.auth.me().subscribe({
      next: (data) => {
        this.user = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.auth.logout();
      }
    });
  }

  // ── Space calculations ─────────────────────────────────────────────────────

  /** Slots used by a single XuxItem (ceil(qty/5)) */
  xuxSlots(item: XuxItem): number {
    return Math.ceil(item.quantity / this.MAX_STACK);
  }

  /** Breakdown of a xux into individual slots with quantity displayed */
  xuxSlotBreakdown(item: XuxItem): number[] {
    const slots: number[] = [];
    let remaining = item.quantity;
    while (remaining > 0) {
      slots.push(Math.min(remaining, this.MAX_STACK));
      remaining -= this.MAX_STACK;
    }
    return slots;
  }

  get totalXuxSlots(): number {
    return this.xuxItems.reduce((sum, i) => sum + this.xuxSlots(i), 0);
  }

  get totalVacunaSlots(): number {
    return this.vacunaItems.reduce((sum, i) => sum + i.quantity, 0);
  }

  get usedSpaces(): number {
    return this.totalXuxSlots + this.totalVacunaSlots;
  }

  get freeSpaces(): number {
    return this.MAX_SPACES - this.usedSpaces;
  }

  get spacePercent(): number {
    return Math.round((this.usedSpaces / this.MAX_SPACES) * 100);
  }

  // ── Stats ──────────────────────────────────────────────────────────────────
  get totalXuxQuantity(): number {
    return this.xuxItems.reduce((s, i) => s + i.quantity, 0);
  }

  get xuxTypeCount(): number {
    return this.xuxItems.filter(i => i.quantity > 0).length;
  }

  get totalVacunaQuantity(): number {
    return this.vacunaItems.reduce((s, i) => s + i.quantity, 0);
  }

  get vacunaTypeCount(): number {
    return this.vacunaItems.filter(i => i.quantity > 0).length;
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  range(n: number): number[] {
    return Array.from({ length: n }, (_, i) => i);
  }

  setTab(tab: 'objetos' | 'vacunas') {
    this.activeTab = tab;
  }

  logout() {
    this.auth.logout();
  }
}
