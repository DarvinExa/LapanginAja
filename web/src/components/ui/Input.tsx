import React from 'react';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string;
  helperText?: string;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, helperText, className = '', id, ...props }, ref) => {
    const inputId = id || `input-${Math.random().toString(36).substring(2, 9)}`;

    return (
      <div className="flex flex-col gap-1.5 w-full">
        <label
          htmlFor={inputId}
          className="text-sm font-semibold text-[#064E3B]"
        >
          {label}
        </label>
        <input
          id={inputId}
          ref={ref}
          className={`w-full px-3 py-2 text-sm bg-[#FDFBF7] border rounded-none transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-[#064E3B] ${
            error
              ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500/20'
              : 'border-[#064E3B] focus:border-[#064E3B]'
          } ${className}`}
          {...props}
        />
        {error ? (
          <span className="text-xs text-rose-600 font-medium">{error}</span>
        ) : (
          helperText && (
            <span className="text-xs text-[#064E3B]/65">{helperText}</span>
          )
        )}
      </div>
    );
  }
);

Input.displayName = 'Input';
