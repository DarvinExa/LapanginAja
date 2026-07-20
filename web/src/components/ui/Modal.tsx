import React, { useEffect } from 'react';

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
}

export const Modal: React.FC<ModalProps> = ({
  isOpen,
  onClose,
  title,
  children,
  footer,
}) => {
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-[#064E3B]/60  transition-opacity"
        onClick={onClose}
      />

      {/* Dialog content */}
      <div className="relative bg-[#FDFBF7] rounded-none shadow-[8px_8px_0_#064E3B] max-w-md w-full overflow-hidden border border-[#064E3B] z-10 animate-scale-in">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-[#064E3B]">
          <h3 className="text-base font-bold text-[#064E3B]">{title}</h3>
          <button
            onClick={onClose}
            className="text-[#064E3B]/45 hover:text-[#064E3B]/80 focus:outline-none text-xl font-semibold cursor-pointer"
          >
            ×
          </button>
        </div>

        {/* Body */}
        <div className="px-5 py-4 text-sm text-[#064E3B]/80 max-h-[70vh] overflow-y-auto">
          {children}
        </div>

        {/* Footer */}
        {footer && (
          <div className="flex justify-end gap-2 px-5 py-3.5 bg-[#FDFBF7] border-t border-[#064E3B]">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
};
