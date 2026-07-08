export default function GuestLayout({ children }) {
    return (
        <div
            className="grid min-h-dvh place-items-center px-4"
            style={{
                backgroundImage:
                    'linear-gradient(oklch(0.89 0.008 260 / 0.45) 1px, transparent 1px), linear-gradient(90deg, oklch(0.89 0.008 260 / 0.45) 1px, transparent 1px)',
                backgroundSize: '24px 24px',
            }}
        >
            <div className="w-full max-w-sm">
                <div className="mb-6 text-center">
                    <p className="font-display text-2xl font-bold tracking-tight text-tinta">metaverso</p>
                    <p className="mt-1 font-mono text-[11px] tracking-widest text-tinta-3 uppercase">
                        campus de prácticas
                    </p>
                </div>
                {children}
            </div>
        </div>
    );
}
