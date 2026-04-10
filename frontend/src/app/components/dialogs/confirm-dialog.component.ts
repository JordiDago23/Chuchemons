import { Component, Input, Output, EventEmitter, ViewChild, ElementRef, AfterViewChecked } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './confirm-dialog.component.html',
  styleUrls: ['./confirm-dialog.component.css']
})
export class ConfirmDialogComponent implements AfterViewChecked {
  @Input() isVisible = false;
  @Input() title = 'Confirmación';
  @Input() message = '¿Estás seguro?';
  @Input() cancelLabel = 'Cancelar';
  @Input() confirmLabel = 'Confirmar';
  
  @Output() confirm = new EventEmitter<void>();
  @Output() cancel = new EventEmitter<void>();

  @ViewChild('confirmBtn') confirmBtn!: ElementRef<HTMLButtonElement>;

  private focusSet = false;

  ngAfterViewChecked() {
    if (this.isVisible && this.confirmBtn && !this.focusSet) {
      this.confirmBtn.nativeElement.focus();
      this.focusSet = true;
    }
    if (!this.isVisible) {
      this.focusSet = false;
    }
  }

  onConfirm() {
    this.confirm.emit();
    this.isVisible = false;
  }

  onCancel() {
    this.cancel.emit();
    this.isVisible = false;
  }

  close(event: MouseEvent) {
    if (event.target === event.currentTarget) {
      this.onCancel();
    }
  }
}
