export default function EmptyState({ title, hint, action }) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-carta border border-dashed border-borde-fuerte px-6 py-12 text-center">
            <p className="text-[15px] font-semibold text-tinta">{title}</p>
            {hint && <p className="max-w-sm text-[13px] text-tinta-3">{hint}</p>}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
