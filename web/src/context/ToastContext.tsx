import React, {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
} from "react";

type ToastType = "success" | "error" | "info" | "warning";

interface Toast {
  id: string;
  message: string;
  type: ToastType;
}

interface ToastContextType {
  addToast: (message: string, type?: ToastType) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const addToast = useCallback((message: string, type: ToastType = "info") => {
    const id = Math.random().toString(36).substring(2, 9);
    setToasts((prev) => [...prev, { id, message, type }]);
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 3000);
  }, []);

  useEffect(() => {
    const handleToastEvent = (e: Event) => {
      const customEvent = e as CustomEvent<{
        message: string;
        type?: ToastType;
      }>;
      if (customEvent.detail) {
        addToast(customEvent.detail.message, customEvent.detail.type || "info");
      }
    };
    window.addEventListener("app-toast", handleToastEvent);
    return () => {
      window.removeEventListener("app-toast", handleToastEvent);
    };
  }, [addToast]);

  return (
    <ToastContext.Provider value={{ addToast }}>
      {children}
      <div className="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none max-w-sm w-full px-4 sm:px-0">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            className={`pointer-events-auto flex items-center justify-between gap-3 px-4 py-3 rounded-none shadow-[4px_4px_0_#064E3B] border text-sm font-medium transition-all duration-300 ${
              toast.type === "success"
                ? "bg-[#10B981]/15 text-[#064E3B] border-[#064E3B]"
                : toast.type === "error"
                  ? "bg-rose-50 text-rose-800 border-rose-200"
                  : toast.type === "warning"
                    ? "bg-amber-50 text-amber-900 border-amber-500"
                    : "bg-[#FDFBF7] text-[#064E3B] border-[#064E3B]"
            }`}
          >
            <span>{toast.message}</span>
            <button
              onClick={() =>
                setToasts((prev) => prev.filter((t) => t.id !== toast.id))
              }
              className="text-[#064E3B]/45 hover:text-[#064E3B]/80 focus:outline-none"
            >
              ×
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
};

export const useToast = () => {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error("useToast must be used within a ToastProvider");
  }
  return context;
};
