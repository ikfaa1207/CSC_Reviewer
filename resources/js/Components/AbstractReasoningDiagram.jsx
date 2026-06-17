import React from 'react';

// Position helper for decorations
const getCoordinates = (position) => {
    switch (position) {
        case 'center': return { x: 50, y: 50 };
        case 'top': return { x: 50, y: 20 };
        case 'bottom': return { x: 50, y: 80 };
        case 'left': return { x: 20, y: 50 };
        case 'right': return { x: 80, y: 50 };
        case 'top-left': return { x: 28, y: 28 };
        case 'top-right': return { x: 72, y: 28 };
        case 'bottom-left': return { x: 28, y: 72 };
        case 'bottom-right': return { x: 72, y: 72 };
        default: return { x: 50, y: 50 };
    }
};

// SVG Shape Component
const ShapePanel = ({ shapes, isBlank, size = 80 }) => {
    if (isBlank) {
        return (
            <div 
                className="flex items-center justify-center border-2 border-dashed border-slate-350 bg-slate-50 dark:bg-slate-800 dark:border-slate-600 rounded-lg shrink-0 shadow-inner"
                style={{ width: `${size}px`, height: `${size}px` }}
            >
                <span className="text-xl font-extrabold text-slate-400 dark:text-slate-500 animate-pulse">?</span>
            </div>
        );
    }

    return (
        <svg 
            viewBox="0 0 100 100" 
            className="border-2 border-slate-200 bg-white dark:bg-slate-900 dark:border-slate-700 text-slate-800 dark:text-slate-200 rounded-lg shrink-0 shadow-sm transition hover:scale-105 hover:shadow-md"
            style={{ width: `${size}px`, height: `${size}px` }}
        >
            <defs>
                {/* Diagonal stripes pattern for shaded shapes */}
                <pattern id="diagonalHatch" width="6" height="6" patternTransform="rotate(45 0 0)" patternUnits="userSpaceOnUse">
                    <line x1="0" y1="0" x2="0" y2="6" stroke="currentColor" strokeWidth="1.2" />
                </pattern>
            </defs>

            {shapes && shapes.map((s, idx) => {
                const rotation = s.rotation || 0;
                const fillMode = s.fill || 'none';
                
                let fillVal = 'none';
                if (fillMode === 'solid') {
                    fillVal = 'currentColor';
                } else if (fillMode === 'shaded') {
                    fillVal = 'url(#diagonalHatch)';
                }

                return (
                    <g key={idx} transform={`rotate(${rotation}, 50, 50)`}>
                        {/* Circle */}
                        {s.shape === 'circle' && (
                            <circle cx={50} cy={50} r={32} fill={fillVal} stroke="currentColor" strokeWidth={2.5} />
                        )}

                        {/* Square */}
                        {s.shape === 'square' && (
                            <rect x={18} y={18} width={64} height={64} rx={4} fill={fillVal} stroke="currentColor" strokeWidth={2.5} />
                        )}

                        {/* Triangle */}
                        {s.shape === 'triangle' && (
                            <polygon points="50,16 84,76 16,76" fill={fillVal} stroke="currentColor" strokeWidth={2.5} />
                        )}

                        {/* Arrow */}
                        {s.shape === 'arrow' && (
                            <g fill={fillVal} stroke="currentColor" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round">
                                <path d="M50,82 L50,18" />
                                <path d="M50,18 L34,34" />
                                <path d="M50,18 L66,34" />
                            </g>
                        )}

                        {/* Cross */}
                        {s.shape === 'cross' && (
                            <g stroke="currentColor" strokeWidth={3} strokeLinecap="round">
                                <line x1={22} y1={22} x2={78} y2={78} />
                                <line x1={78} y1={22} x2={22} y2={78} />
                            </g>
                        )}

                        {/* Line */}
                        {s.shape === 'line' && (
                            <line x1={15} y1={50} x2={85} y2={50} stroke="currentColor" strokeWidth={3} strokeLinecap="round" />
                        )}

                        {/* Star */}
                        {s.shape === 'star' && (
                            <polygon 
                                points="50,15 61,38 86,38 66,54 74,79 50,63 26,79 34,54 14,38 39,38" 
                                fill={fillVal} 
                                stroke="currentColor" 
                                strokeWidth={2.5} 
                                strokeLinejoin="round"
                            />
                        )}

                        {/* Inner decorations inside this shape */}
                        {s.decorations && s.decorations.map((d, dIdx) => {
                            const { x, y } = getCoordinates(d.position);
                            
                            if (d.type === 'dot') {
                                return <circle key={dIdx} cx={x} cy={y} r={5.5} fill="currentColor" />;
                            }
                            
                            if (d.type === 'line') {
                                const from = getCoordinates(d.from || 'center');
                                const to = getCoordinates(d.to || 'top');
                                return <line key={dIdx} x1={from.x} y1={from.y} x2={to.x} y2={to.y} stroke="currentColor" strokeWidth={2} strokeLinecap="round" />;
                            }

                            if (d.type === 'arrow') {
                                const from = getCoordinates(d.from || 'center');
                                const to = getCoordinates(d.to || 'top');
                                return (
                                    <g key={dIdx} stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
                                        <line x1={from.x} y1={from.y} x2={to.x} y2={to.y} />
                                        {/* Simple arrowhead towards to */}
                                        <circle cx={to.x} cy={to.y} r={3} fill="currentColor" />
                                    </g>
                                );
                            }

                            return null;
                        })}
                    </g>
                );
            })}
        </svg>
    );
};

// Main Diagram Container
export default function AbstractReasoningDiagram({ schema }) {
    if (!schema) return null;

    // Direct single panel mode
    if (schema.shapes && !schema.type) {
        return (
            <div className="inline-block p-1">
                <ShapePanel shapes={schema.shapes} isBlank={schema.blank} size={64} />
            </div>
        );
    }

    const size = schema.type === 'grid' ? 76 : 84;

    return (
        <div className="flex flex-col items-center my-6 select-none bg-slate-50/50 dark:bg-slate-800/20 p-5 rounded-2xl border border-slate-100 dark:border-slate-800 max-w-full overflow-x-auto">
            {/* Sequence/Series Layout */}
            {schema.type === 'sequence' && (
                <div className="flex items-center gap-2.5 min-w-max py-2">
                    {schema.steps && schema.steps.map((step, idx) => (
                        <React.Fragment key={idx}>
                            <ShapePanel shapes={step.shapes} isBlank={step.blank} size={size} />
                            {idx < schema.steps.length - 1 && (
                                <span className="text-lg font-bold text-slate-450 dark:text-slate-500 font-mono shrink-0 select-none px-1">→</span>
                            )}
                        </React.Fragment>
                    ))}
                </div>
            )}

            {/* Grid/Matrix Layout */}
            {schema.type === 'grid' && (
                <div 
                    className="grid gap-3 p-1.5 bg-slate-100 dark:bg-slate-850 rounded-xl"
                    style={{ 
                        gridTemplateColumns: `repeat(${schema.cols || 3}, minmax(0, 1fr))` 
                    }}
                >
                    {schema.cells && schema.cells.map((cell, idx) => (
                        <ShapePanel key={idx} shapes={cell.shapes} isBlank={cell.blank} size={size} />
                    ))}
                </div>
            )}
        </div>
    );
}

// Question Content Parser Helper
export function renderQuestionContent(text) {
    if (!text) return null;

    // Split text by [diagram]...[/diagram] tags (case-insensitive, dotAll flag via /s)
    const parts = text.split(/(\[diagram\].*?\[\/diagram\])/is);

    return (
        <>
            {parts.map((part, index) => {
                if (part.toLowerCase().startsWith('[diagram]') && part.toLowerCase().endsWith('[/diagram]')) {
                    const jsonStr = part.substring(9, part.length - 10);
                    try {
                        const schema = JSON.parse(jsonStr.trim());
                        return <AbstractReasoningDiagram key={index} schema={schema} />;
                    } catch (e) {
                        console.error("Failed to parse diagram JSON:", e, jsonStr);
                        return <pre key={index} className="text-xs text-red-500 bg-red-50 dark:bg-red-950/20 p-2 rounded">{jsonStr}</pre>;
                    }
                }
                
                // Render text segments, handling linebreaks cleanly
                return (
                    <span key={index} className="whitespace-pre-wrap">
                        {part}
                    </span>
                );
            })}
        </>
    );
}
