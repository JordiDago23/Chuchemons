import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { MochilaService, MochilaResponse } from './mochila.service';

const EMPTY_INVENTORY: MochilaResponse = {
  items: [],
  used_spaces: 0,
  max_spaces: 20,
  free_spaces: 20,
};

@Injectable({ providedIn: 'root' })
export class InventoryService {
  private inventorySubject = new BehaviorSubject<MochilaResponse>(EMPTY_INVENTORY);

  /** Observable amb l'estat actual de la motxilla */
  readonly inventory$ = this.inventorySubject.asObservable();

  constructor(private mochilaService: MochilaService) {}

  /** Carrega l'inventari des del backend i notifica els subscriptors */
  load(): void {
    this.mochilaService.getMochila().subscribe({
      next: (res) => this.inventorySubject.next(res),
      error: () => { /* mantenir l'estat anterior */ },
    });
  }

  /** Valor síncron actual (snapshot) */
  get snapshot(): MochilaResponse {
    return this.inventorySubject.getValue();
  }
}
