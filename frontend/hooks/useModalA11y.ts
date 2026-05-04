import { useEffect, useRef } from 'react';

/**
 * FE-12 minimum a11y fix for modal dialogs.
 *
 * Provides three things every modal should have:
 *   1. ESC key closes the modal.
 *   2. A focus trap — Tab/Shift-Tab cycle stays inside the modal.
 *   3. Auto-focus the first focusable element on mount (so screen readers
 *      land somewhere meaningful instead of starting at the page top).
 *
 * The container element should be the modal's outermost focusable wrapper
 * (commonly the white card, NOT the backdrop). Returns a ref to attach to it.
 *
 * Caller responsibility:
 *   - Set role="dialog" aria-modal="true" + aria-labelledby on the wrapper
 *     (a11y screen-reader hints; this hook only handles keyboard behaviour).
 *
 * Usage:
 *   const ref = useModalA11y(open, onClose);
 *   return <div ref={ref} role="dialog" aria-modal="true">...</div>;
 *
 * Coordinated WCAG audit (full a11y compliance) is deferred to a v2 sprint;
 * this minimum makes EdgeRX usable with a keyboard.
 */
export function useModalA11y(
  isOpen: boolean,
  onClose: () => void,
): React.RefObject<HTMLDivElement> {
  const containerRef = useRef<HTMLDivElement>(null);

  // 1. ESC to close
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [isOpen, onClose]);

  // 2. Focus trap
  useEffect(() => {
    if (!isOpen || !containerRef.current) return;
    const container = containerRef.current;

    const getFocusable = (): HTMLElement[] => {
      return Array.from(
        container.querySelectorAll<HTMLElement>(
          'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
      ).filter(el => el.offsetParent !== null);
    };

    const handler = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return;
      const items = getFocusable();
      if (items.length === 0) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [isOpen]);

  // 3. Auto-focus first focusable element on mount
  useEffect(() => {
    if (!isOpen || !containerRef.current) return;
    const t = setTimeout(() => {
      const items = containerRef.current?.querySelectorAll<HTMLElement>(
        'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled])'
      );
      if (items && items.length > 0) items[0].focus();
    }, 50);
    return () => clearTimeout(t);
  }, [isOpen]);

  return containerRef;
}
