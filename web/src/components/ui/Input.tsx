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
          className="text-sm font-semibold text-slate-800"
        >
          {label}
        </label>
        <input
          id={inputId}
          ref={ref}
          className={`w-full px-3 py-2 text-sm bg-white border rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 ${
            error
              ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500/20'
              : 'border-slate-200 focus:border-emerald-600'
          } ${className}`}
          {...props}
        />
        {error ? (
          <span className="text-xs text-rose-600 font-medium">{error}</span>
        ) : (
          helperText && (
            <span className="text-xs text-slate-500">{helperText}</span>
          )
        )}
      </div>
    );
  }
);

Input.displayName = 'Input';
