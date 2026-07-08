export default function Table({ head = [], children }) {
    return (
        <div className="overflow-x-auto rounded-carta bg-superficie ring-1 ring-borde">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-borde">
                        {head.map((h) => (
                            <th
                                key={h}
                                className="px-4 py-2.5 text-[11px] font-semibold tracking-wide text-tinta-3 uppercase"
                            >
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-borde">{children}</tbody>
            </table>
        </div>
    );
}
