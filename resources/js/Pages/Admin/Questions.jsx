import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, useCallback, useRef } from 'react';
import ConfirmationModal from '@/Components/ConfirmationModal';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { renderQuestionContent } from '@/Components/AbstractReasoningDiagram';
import axios from 'axios';
import {
    faPlus,
    faWandMagicSparkles,
    faPen,
    faTrash,
    faSearch,
    faSpinner,
    faSave,
    faBan,
    faCheck,
    faFileAlt,
    faFilter,
    faExclamationTriangle,
    faChevronDown,
    faChevronUp,
    faSort,
    faChevronLeft,
    faChevronRight,
} from '@fortawesome/free-solid-svg-icons';

// ─── Helper: format date ────────────────────────────────────────────────────
function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: '2-digit' });
}

// ─── Truncate text ──────────────────────────────────────────────────────────
function truncate(text, len = 72) {
    if (!text) return '';
    const clean = text.replace(/\s*\(Variation ID:\s*\d+\)/gi, '');
    return clean.length > len ? clean.slice(0, len) + '…' : clean;
}

// ─── Audit Status Badge ─────────────────────────────────────────────────────
function AuditBadge({ status }) {
    if (!status || status === 'passed') return null;
    const isStruct = status === 'failed_structure';
    return (
        <span className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xxs font-bold uppercase tracking-wider animate-pulse
            ${isStruct
                ? 'bg-orange-50 text-orange-800 dark:bg-orange-950/30 dark:text-orange-300'
                : 'bg-red-50 text-red-800 dark:bg-red-950/30 dark:text-red-300'}`}>
            <FontAwesomeIcon icon={faExclamationTriangle} className="w-2.5 h-2.5" />
            {isStruct ? 'Structural' : 'Factual'}
        </span>
    );
}

// ─── Duplicate Badge ────────────────────────────────────────────────────────
function DupBadge() {
    return (
        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xxs font-bold bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300 uppercase tracking-wider animate-pulse">
            <FontAwesomeIcon icon={faExclamationTriangle} className="w-2.5 h-2.5" />
            Dup
        </span>
    );
}

// ─── Pagination Controls ────────────────────────────────────────────────────
function Pagination({ meta, filters, onNavigate }) {
    if (!meta || meta.last_page <= 1) return null;
    const { current_page, last_page, from, to, total } = meta;

    const pages = [];
    const delta = 2;
    for (let p = Math.max(1, current_page - delta); p <= Math.min(last_page, current_page + delta); p++) {
        pages.push(p);
    }

    return (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-slate-100 dark:border-slate-700 px-1">
            <p className="text-xs text-slate-500 dark:text-slate-400">
                Showing <span className="font-semibold text-slate-700 dark:text-slate-300">{from}–{to}</span> of <span className="font-semibold text-slate-700 dark:text-slate-300">{total}</span> questions
            </p>
            <div className="flex items-center gap-1.5">
                <button
                    disabled={current_page === 1}
                    onClick={() => onNavigate(current_page - 1)}
                    className="h-8 w-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 transition"
                >
                    <FontAwesomeIcon icon={faChevronLeft} className="w-3 h-3" />
                </button>

                {pages[0] > 1 && (
                    <>
                        <button onClick={() => onNavigate(1)} className="h-8 w-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition">1</button>
                        {pages[0] > 2 && <span className="text-slate-400 text-xs px-1">…</span>}
                    </>
                )}

                {pages.map(p => (
                    <button
                        key={p}
                        onClick={() => onNavigate(p)}
                        className={`h-8 w-8 flex items-center justify-center rounded-lg border text-xs font-semibold transition
                            ${p === current_page
                                ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'}`}
                    >
                        {p}
                    </button>
                ))}

                {pages[pages.length - 1] < last_page && (
                    <>
                        {pages[pages.length - 1] < last_page - 1 && <span className="text-slate-400 text-xs px-1">…</span>}
                        <button onClick={() => onNavigate(last_page)} className="h-8 w-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition">{last_page}</button>
                    </>
                )}

                <button
                    disabled={current_page === last_page}
                    onClick={() => onNavigate(current_page + 1)}
                    className="h-8 w-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 transition"
                >
                    <FontAwesomeIcon icon={faChevronRight} className="w-3 h-3" />
                </button>

                {/* Per-page selector */}
                <select
                    value={filters.per_page}
                    onChange={e => onNavigate(1, { per_page: e.target.value })}
                    className="ml-2 h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    <option value="25">25/page</option>
                    <option value="50">50/page</option>
                    <option value="100">100/page</option>
                </select>
            </div>
        </div>
    );
}

// ─── Progress Bar ───────────────────────────────────────────────────────────
function GenerationProgress({ progress }) {
    const pct = progress.total > 0 ? Math.round((progress.current / progress.total) * 100) : 0;
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between text-xs">
                <span className="font-semibold text-slate-700 dark:text-slate-300">
                    Generating… {progress.saved} / {progress.total} saved
                </span>
                <span className="font-bold text-slate-500 dark:text-slate-400">{pct}%</span>
            </div>
            <div className="w-full h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div
                    className="h-full bg-gradient-to-r from-indigo-500 to-violet-500 rounded-full transition-all duration-500 ease-out"
                    style={{ width: `${pct}%` }}
                />
            </div>
            {progress.skipped > 0 && (
                <p className="text-xxs text-slate-500 dark:text-slate-400">
                    {progress.skipped} skipped (duplicates/invalid)
                    {progress.structuralErrors > 0 && ` · ${progress.structuralErrors} structural error(s) auto-flagged`}
                </p>
            )}
            {progress.structuralErrors > 0 && progress.skipped === 0 && (
                <p className="text-xxs text-orange-600 dark:text-orange-400">
                    {progress.structuralErrors} structural error(s) auto-flagged after generation
                </p>
            )}
        </div>
    );
}

// ════════════════════════════════════════════════════════════════════════════
export default function Questions({ questions, categories, filters, totalCount, duplicateCount, auditIssuesCount }) {

    // questions is now a Laravel paginator: { data, links, meta }
    const questionList = questions?.data ?? [];
    const paginatorMeta = questions?.meta ?? null;

    const [editingQuestion, setEditingQuestion] = useState(null);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [questionToDelete, setQuestionToDelete] = useState(null);
    const [cleanModalOpen, setCleanModalOpen] = useState(false);
    const [auditing, setAuditing] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkDeleteModalOpen, setBulkDeleteModalOpen] = useState(false);
    const [expandedIds, setExpandedIds] = useState([]);
    const [fixingQuestionId, setFixingQuestionId] = useState(null);

    // AI generation progress
    const [generating, setGenerating] = useState(false);
    const [progress, setProgress] = useState({ saved: 0, skipped: 0, structuralErrors: 0, current: 0, total: 0 });
    const abortRef = useRef(false);

    // Search debounce ref
    const searchTimeout = useRef(null);

    const toggleExpand = (id) => {
        setExpandedIds(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);
    };

    // ── Navigate pages / apply filters ─────────────────────────────────────
    const navigate = useCallback((page = 1, extraParams = {}) => {
        router.get(
            route('admin.questions.index'),
            { ...filters, page, ...extraParams },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, [filters]);

    const applyFilter = useCallback((params) => {
        router.get(
            route('admin.questions.index'),
            { ...filters, page: 1, ...params },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, [filters]);

    const handleSearch = (value) => {
        clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => applyFilter({ search: value }), 400);
    };

    // ── AI suggestion fix ──────────────────────────────────────────────────
    const handleSuggestFix = (question) => {
        setFixingQuestionId(question.id);

        axios.post(route('admin.questions.suggestFix', question.id))
            .then(response => {
                const fixData = response.data;
                setEditingQuestion(question);
                clearErrors();

                setData({
                    exam_category_id: fixData.exam_category_id,
                    question_text: fixData.question_text,
                    explanation: fixData.explanation || '',
                    options: fixData.options.map(opt => ({
                        option_text: opt.option_text,
                        is_correct: !!opt.is_correct,
                    })),
                });

                const formEl = document.getElementById('form_category');
                if (formEl) formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .catch(error => {
                console.error('AI suggested fix failed:', error);
                alert(error.response?.data?.error || 'Failed to fetch AI suggested corrections. Please verify configuration or logs.');
            })
            .finally(() => setFixingQuestionId(null));
    };

    // ── Inertia Form hook for Add/Edit ─────────────────────────────────────
    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        exam_category_id: '',
        question_text: '',
        explanation: '',
        options: [
            { option_text: '', is_correct: true },
            { option_text: '', is_correct: false },
            { option_text: '', is_correct: false },
            { option_text: '', is_correct: false },
        ],
    });

    // ── Inertia Form hook for AI generation ───────────────────────────────
    const aiForm = useForm({ exam_category_id: '', level: 'professional', count: 5 });

    const handleOptionTextChange = (index, value) => {
        const newOptions = [...data.options];
        newOptions[index].option_text = value;
        setData('options', newOptions);
    };

    const handleOptionCorrectToggle = (index) => {
        setData('options', data.options.map((opt, idx) => ({ ...opt, is_correct: idx === index })));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingQuestion) {
            put(route('admin.questions.update', editingQuestion.id), { onSuccess: () => cancelEdit() });
        } else {
            post(route('admin.questions.store'), { onSuccess: () => resetForm() });
        }
    };

    const handleAILevelChange = (newLevel) => {
        if (aiForm.data.exam_category_id === 'all') {
            aiForm.setData({ ...aiForm.data, level: newLevel });
            return;
        }
        const selectedCat = categories.find(c => c.id === parseInt(aiForm.data.exam_category_id));
        const keepCategory = selectedCat && (selectedCat.level === 'both' || selectedCat.level === newLevel);
        aiForm.setData({ ...aiForm.data, level: newLevel, exam_category_id: keepCategory ? aiForm.data.exam_category_id : '' });
    };

    // ── Chunked AI generation ─────────────────────────────────────────────
    const handleAIGenerate = async (e) => {
        e.preventDefault();
        const totalCount = parseInt(aiForm.data.count);
        const chunkSize = 5;
        const totalChunks = Math.ceil(totalCount / chunkSize);

        setGenerating(true);
        abortRef.current = false;
        setProgress({ saved: 0, skipped: 0, structuralErrors: 0, current: 0, total: totalCount });

        let totalSaved = 0;
        let totalSkipped = 0;
        let totalStructuralErrors = 0;

        try {
            for (let i = 0; i < totalChunks; i++) {
                if (abortRef.current) break;

                const thisChunkSize = Math.min(chunkSize, totalCount - i * chunkSize);

                const response = await axios.post(route('admin.questions.generateAI'), {
                    exam_category_id: aiForm.data.exam_category_id,
                    level: aiForm.data.level,
                    count: totalCount,
                    chunk_index: i,
                    chunk_size: thisChunkSize,
                });

                const result = response.data;
                totalSaved += result.saved ?? 0;
                totalSkipped += result.skipped ?? 0;
                totalStructuralErrors += result.structuralErrors ?? 0;

                setProgress({
                    saved: totalSaved,
                    skipped: totalSkipped,
                    structuralErrors: totalStructuralErrors,
                    current: Math.min((i + 1) * chunkSize, totalCount),
                    total: totalCount,
                });
            }
        } catch (err) {
            console.error('Chunk generation error:', err);
            alert(err.response?.data?.error || 'AI generation failed. Check logs.');
        } finally {
            setGenerating(false);
            // Reload the question list to show new questions
            router.reload({ only: ['questions', 'totalCount', 'duplicateCount', 'auditIssuesCount'] });
        }
    };

    const startEdit = (question) => {
        setEditingQuestion(question);
        clearErrors();

        const formOptions = question.options.map(opt => ({
            option_text: opt.option_text,
            is_correct: !!opt.is_correct,
        }));
        while (formOptions.length < 4) formOptions.push({ option_text: '', is_correct: false });

        setData({
            exam_category_id: question.exam_category_id,
            question_text: question.question_text.replace(/\s*\(Variation ID:\s*\d+\)/gi, ''),
            explanation: question.explanation || '',
            options: formOptions,
        });
    };

    const cancelEdit = () => { setEditingQuestion(null); resetForm(); };
    const resetForm = () => { clearErrors(); reset(); };

    const triggerDelete = (id) => { setQuestionToDelete(id); setDeleteModalOpen(true); };

    const confirmDelete = () => {
        if (questionToDelete) {
            router.delete(route('admin.questions.destroy', questionToDelete), {
                onSuccess: () => setQuestionToDelete(null),
            });
        }
    };

    const handleCleanDuplicates = () => {
        router.post(route('admin.questions.cleanDuplicates'), {}, { onSuccess: () => setCleanModalOpen(false) });
    };

    const handleRunAudit = () => {
        setAuditing(true);
        router.post(route('admin.questions.runAudit'), {}, { onFinish: () => setAuditing(false) });
    };

    const handleToggleSelectAll = () => {
        setSelectedIds(prev =>
            prev.length === questionList.length ? [] : questionList.map(q => q.id)
        );
    };

    const handleBulkDelete = () => {
        router.delete(route('admin.questions.bulkDestroy'), {
            data: { ids: selectedIds },
            onSuccess: () => { setSelectedIds([]); setBulkDeleteModalOpen(false); },
        });
    };

    const filteredAICategories = categories.filter(cat =>
        cat.level === 'both' || cat.level === aiForm.data.level
    );

    const SORT_OPTIONS = [
        { value: 'newest',          label: 'Newest First' },
        { value: 'oldest',          label: 'Oldest First' },
        { value: 'category_asc',    label: 'Category A → Z' },
        { value: 'category_desc',   label: 'Category Z → A' },
        { value: 'duplicates_first', label: 'Duplicates First' },
        { value: 'flagged_first',   label: 'Flagged First' },
    ];

    const FILTER_OPTIONS = [
        { value: 'all',              label: 'All Statuses' },
        { value: 'passed',           label: 'Passed Only' },
        { value: 'failed_structure', label: 'Structural Errors' },
        { value: 'failed_facts',     label: 'Factual Errors' },
        { value: 'duplicates',       label: 'Duplicates Only' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <h2 className="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <FontAwesomeIcon icon={faFileAlt} className="text-slate-600 dark:text-slate-400" />
                        Question Bank Manager
                    </h2>
                    <div className="flex flex-wrap items-center gap-1.5 mt-2 sm:mt-0 select-none">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xxs font-bold bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 uppercase tracking-wider">
                            Total: {totalCount}
                        </span>
                        {duplicateCount > 0 && (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xxs font-bold bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60 uppercase tracking-wider animate-pulse">
                                Duplicates: {duplicateCount}
                            </span>
                        )}
                        {auditIssuesCount > 0 && (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xxs font-bold bg-rose-50 text-rose-800 dark:bg-rose-900/30 dark:text-rose-350 border border-rose-200 dark:border-rose-900/60 uppercase tracking-wider animate-pulse">
                                Flagged: {auditIssuesCount}
                            </span>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Admin Question Manager" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                    {/* Master Split Grid Layout */}
                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">

                        {/* ── Left Column: Form + AI Widget ─────────────────── */}
                        <div className="lg:col-span-1 space-y-5">

                            {/* AI Generation Widget */}
                            {!editingQuestion && (
                                <div className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700 shadow-sm">
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-slate-100 mb-1.5 flex items-center gap-2">
                                        <FontAwesomeIcon icon={faWandMagicSparkles} className="text-indigo-500 dark:text-indigo-400" />
                                        AI Question Generator
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
                                        Generate high-quality CSE questions matching category, level, and quantity.
                                    </p>

                                    <form onSubmit={handleAIGenerate} className="space-y-3.5">
                                        <div>
                                            <label htmlFor="ai_level" className="block text-xxs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Level</label>
                                            <select
                                                id="ai_level"
                                                value={aiForm.data.level}
                                                onChange={(e) => handleAILevelChange(e.target.value)}
                                                className="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                required
                                                disabled={generating}
                                            >
                                                <option value="professional">Professional</option>
                                                <option value="sub_professional">Sub-Professional</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label htmlFor="ai_category" className="block text-xxs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Category</label>
                                            <select
                                                id="ai_category"
                                                value={aiForm.data.exam_category_id}
                                                onChange={(e) => aiForm.setData('exam_category_id', e.target.value)}
                                                className="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                required
                                                disabled={generating}
                                            >
                                                <option value="">Select Category</option>
                                                <option value="all">All Categories Combined</option>
                                                {filteredAICategories.map((cat) => (
                                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label htmlFor="ai_count" className="block text-xxs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Quantity</label>
                                            <select
                                                id="ai_count"
                                                value={aiForm.data.count}
                                                onChange={(e) => aiForm.setData('count', e.target.value)}
                                                className="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                required
                                                disabled={generating}
                                            >
                                                <option value="1">1 Question</option>
                                                <option value="5">5 Questions</option>
                                                <option value="10">10 Questions</option>
                                                <option value="15">15 Questions</option>
                                                <option value="20">20 Questions</option>
                                                <option value="30">30 Questions</option>
                                                <option value="40">40 Questions</option>
                                                <option value="50">50 Questions</option>
                                                <option value="150">150 Questions</option>
                                            </select>
                                        </div>

                                        {/* Progress bar */}
                                        {generating && <GenerationProgress progress={progress} />}

                                        <button
                                            type="submit"
                                            disabled={generating}
                                            className="w-full inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-60 dark:bg-indigo-600 dark:hover:bg-indigo-500 transition"
                                        >
                                            {generating ? (
                                                <>
                                                    <FontAwesomeIcon icon={faSpinner} className="animate-spin mr-2" />
                                                    Generating in Chunks…
                                                </>
                                            ) : (
                                                <>
                                                    <FontAwesomeIcon icon={faWandMagicSparkles} className="mr-1.5" />
                                                    AI Generate & Save
                                                </>
                                            )}
                                        </button>
                                    </form>
                                </div>
                            )}

                            {/* Add / Edit Form */}
                            <div className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700 shadow-sm">
                                <h3 className="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
                                    <FontAwesomeIcon icon={editingQuestion ? faPen : faPlus} className="text-slate-600 dark:text-slate-400" />
                                    {editingQuestion ? 'Edit Question' : 'Add New Question'}
                                </h3>

                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div>
                                        <label htmlFor="form_category" className="block text-xxs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Exam Category</label>
                                        <select
                                            id="form_category"
                                            value={data.exam_category_id}
                                            onChange={(e) => setData('exam_category_id', e.target.value)}
                                            className="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                            required
                                        >
                                            <option value="">Select Category</option>
                                            {categories.map((cat) => (
                                                <option key={cat.id} value={cat.id}>
                                                    {cat.name} ({cat.level === 'both' ? 'Both' : cat.level.replace('_', ' ')})
                                                </option>
                                            ))}
                                        </select>
                                        {errors.exam_category_id && <p className="text-xs text-red-600 mt-1">{errors.exam_category_id}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="form_text" className="block text-xxs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Question Text</label>
                                        <textarea
                                            id="form_text"
                                            rows="4"
                                            value={data.question_text}
                                            onChange={(e) => setData('question_text', e.target.value)}
                                            placeholder="Enter multiple choice question..."
                                            className="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                            required
                                        />
                                        {errors.question_text && <p className="text-xs text-red-600 mt-1">{errors.question_text}</p>}
                                    </div>

                                    <div className="space-y-2.5 pt-1">
                                        <span className="block text-xxs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">
                                            Answer Options <span className="normal-case font-normal">(select correct)</span>
                                        </span>
                                        {errors.options && <p className="text-xs text-red-600 font-medium">{errors.options}</p>}

                                        {data.options.map((option, idx) => {
                                            const label = String.fromCharCode(65 + idx);
                                            return (
                                                <div key={idx} className="flex items-center gap-2.5">
                                                    <input
                                                        type="radio"
                                                        name="correct-option"
                                                        checked={option.is_correct}
                                                        onChange={() => handleOptionCorrectToggle(idx)}
                                                        className="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:checked:bg-slate-100"
                                                        title="Mark as correct option"
                                                    />
                                                    <div className="flex-1 relative">
                                                        <span className={`absolute left-3 top-2.5 text-xs font-bold ${option.is_correct ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'}`}>
                                                            {label}
                                                        </span>
                                                        <input
                                                            type="text"
                                                            value={option.option_text}
                                                            onChange={(e) => handleOptionTextChange(idx, e.target.value)}
                                                            placeholder={`Option ${label}`}
                                                            className={`block w-full rounded-lg pl-8 pr-3 py-2 text-xs text-slate-800 shadow-sm focus:ring-1 dark:text-slate-100 dark:bg-slate-900 transition
                                                                ${option.is_correct
                                                                    ? 'border-emerald-400 bg-emerald-50/30 focus:border-emerald-500 focus:ring-emerald-300 dark:border-emerald-600/40 dark:bg-emerald-950/20'
                                                                    : 'border-slate-200 bg-white focus:border-slate-400 focus:ring-slate-300 dark:border-slate-700'}`}
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    <div>
                                        <label htmlFor="form_explanation" className="block text-xxs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Explanation <span className="normal-case font-normal">(Review Mode)</span></label>
                                        <textarea
                                            id="form_explanation"
                                            rows="3"
                                            value={data.explanation}
                                            onChange={(e) => setData('explanation', e.target.value)}
                                            placeholder="Provide reasoning/calculations for the correct choice..."
                                            className="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        />
                                        {errors.explanation && <p className="text-xs text-red-600 mt-1">{errors.explanation}</p>}
                                    </div>

                                    <div className="flex gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="flex-1 inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                                        >
                                            {processing ? <FontAwesomeIcon icon={faSpinner} className="animate-spin mr-1.5" /> : <FontAwesomeIcon icon={faSave} className="mr-1.5" />}
                                            {editingQuestion ? 'Update Question' : 'Save Question'}
                                        </button>
                                        {editingQuestion && (
                                            <button
                                                type="button"
                                                onClick={cancelEdit}
                                                className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition"
                                            >
                                                <FontAwesomeIcon icon={faBan} className="mr-1.5" />
                                                Cancel
                                            </button>
                                        )}
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* ── Right Column: Filters + Table ─────────────────── */}
                        <div className="lg:col-span-2 space-y-4">

                            {/* Filter/Sort/Search bar */}
                            <div className="bg-white border border-slate-200 rounded-xl p-3.5 dark:bg-slate-800 dark:border-slate-700 shadow-sm">
                                <div className="flex flex-wrap gap-2.5 items-center">
                                    {/* Search */}
                                    <div className="flex-1 min-w-[180px] relative">
                                        <input
                                            type="text"
                                            defaultValue={filters.search}
                                            onChange={(e) => handleSearch(e.target.value)}
                                            placeholder="Search questions…"
                                            className="block w-full rounded-lg border-slate-200 bg-white pl-4 pr-9 py-2 text-sm text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        />
                                        <FontAwesomeIcon icon={faSearch} className="absolute right-3 top-2.5 text-slate-400 dark:text-slate-500 w-3.5 h-3.5" />
                                    </div>

                                    {/* Category filter */}
                                    <div className="relative shrink-0">
                                        <FontAwesomeIcon icon={faFilter} className="absolute left-3 top-2.5 text-slate-400 dark:text-slate-500 w-3.5 h-3.5 pointer-events-none" />
                                        <select
                                            value={filters.category_id}
                                            onChange={(e) => applyFilter({ category_id: e.target.value })}
                                            className="pl-8 pr-3 py-2 rounded-lg border border-slate-200 bg-white text-sm text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        >
                                            <option value="">All Categories</option>
                                            {categories.map((cat) => (
                                                <option key={cat.id} value={cat.id}>{cat.name}</option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Status filter */}
                                    <div className="relative shrink-0">
                                        <select
                                            value={filters.filter}
                                            onChange={(e) => applyFilter({ filter: e.target.value })}
                                            className="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        >
                                            {FILTER_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                        </select>
                                    </div>

                                    {/* Sort */}
                                    <div className="relative shrink-0">
                                        <FontAwesomeIcon icon={faSort} className="absolute left-3 top-2.5 text-slate-400 dark:text-slate-500 w-3.5 h-3.5 pointer-events-none" />
                                        <select
                                            value={filters.sort}
                                            onChange={(e) => applyFilter({ sort: e.target.value })}
                                            className="pl-8 pr-3 py-2 rounded-lg border border-slate-200 bg-white text-sm text-slate-800 shadow-sm focus:border-slate-400 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        >
                                            {SORT_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                        </select>
                                    </div>

                                    {/* Audit button */}
                                    <button
                                        onClick={handleRunAudit}
                                        disabled={auditing}
                                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition shrink-0"
                                        title="Audit all un-passed questions structurally and factually using AI"
                                    >
                                        {auditing ? (
                                            <><FontAwesomeIcon icon={faSpinner} className="animate-spin mr-1.5" />Auditing…</>
                                        ) : (
                                            <><FontAwesomeIcon icon={faWandMagicSparkles} className="mr-1.5 text-indigo-500" />Run Audit</>
                                        )}
                                    </button>
                                </div>
                            </div>

                            {/* Duplicate Warning Banner */}
                            {duplicateCount > 0 && (
                                <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 dark:bg-amber-950/20 dark:border-amber-900/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
                                    <div className="flex items-start gap-3">
                                        <span className="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 shrink-0">
                                            <FontAwesomeIcon icon={faExclamationTriangle} className="w-4 h-4" />
                                        </span>
                                        <div>
                                            <h4 className="text-sm font-bold text-amber-900 dark:text-amber-300">Duplicate Questions Detected</h4>
                                            <p className="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                                                <strong>{duplicateCount}</strong> duplicate question(s) found. Auto-Clean will keep the oldest entry.
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => setCleanModalOpen(true)}
                                        className="inline-flex items-center justify-center rounded-lg bg-amber-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 transition shrink-0 animate-pulse hover:animate-none"
                                    >
                                        <FontAwesomeIcon icon={faTrash} className="mr-1.5 w-3 h-3" />
                                        Auto-Clean Duplicates
                                    </button>
                                </div>
                            )}

                            {/* Audit Issues Warning Banner */}
                            {auditIssuesCount > 0 && (
                                <div className="bg-red-50 border border-red-200 rounded-xl p-4 dark:bg-red-950/20 dark:border-red-900/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
                                    <div className="flex items-start gap-3">
                                        <span className="flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 shrink-0">
                                            <FontAwesomeIcon icon={faExclamationTriangle} className="w-4 h-4" />
                                        </span>
                                        <div>
                                            <h4 className="text-sm font-bold text-red-900 dark:text-red-300">Integrity Issues Flagged</h4>
                                            <p className="text-xs text-red-700 dark:text-red-400 mt-0.5">
                                                <strong>{auditIssuesCount}</strong> question(s) flagged with structural or factual errors.
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => router.post(route('admin.questions.bulkFixAudit'))}
                                        className="inline-flex items-center justify-center rounded-lg bg-red-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-700 transition shrink-0"
                                    >
                                        <FontAwesomeIcon icon={faWandMagicSparkles} className="mr-1.5 w-3 h-3" />
                                        AI Bulk Fix All
                                    </button>
                                </div>
                            )}

                            {/* Table header row: count + select-all + expand controls */}
                            <div className="flex items-center justify-between px-1">
                                <p className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    {paginatorMeta
                                        ? `${paginatorMeta.from ?? 0}–${paginatorMeta.to ?? 0} of ${paginatorMeta.total} questions`
                                        : '0 questions'}
                                    {(filters.search || filters.category_id || filters.filter !== 'all') ? ' (filtered)' : ''}
                                </p>
                                {questionList.length > 0 && (
                                    <div className="flex items-center gap-3 select-none">
                                        <button
                                            type="button"
                                            onClick={handleToggleSelectAll}
                                            className="text-xxs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                                        >
                                            {selectedIds.length === questionList.length ? 'Deselect All' : 'Select Page'}
                                        </button>
                                        <span className="text-slate-300 dark:text-slate-600 text-xxs">|</span>
                                        <button
                                            type="button"
                                            onClick={() => setExpandedIds(
                                                expandedIds.length === questionList.length
                                                    ? []
                                                    : questionList.map(q => q.id)
                                            )}
                                            className="text-xxs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                                        >
                                            {expandedIds.length === questionList.length ? 'Collapse All' : 'Expand All'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* ── Compact Data Table ───────────────────────── */}
                            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden dark:bg-slate-800 dark:border-slate-700 shadow-sm">
                                {questionList.length === 0 ? (
                                    <div className="p-10 text-center text-slate-500 dark:text-slate-400 text-sm">
                                        No questions found matching your filters.
                                    </div>
                                ) : (
                                    <>
                                        {/* Table head */}
                                        <div className="grid grid-cols-[auto_1fr_auto_auto_auto_auto] gap-0 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-xxs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 px-4 py-2.5">
                                            <div className="w-7"></div>
                                            <div>Question</div>
                                            <div className="text-center w-28 hidden sm:block">Category</div>
                                            <div className="text-center w-20 hidden md:block">Status</div>
                                            <div className="text-center w-16 hidden lg:block">Date</div>
                                            <div className="text-right w-24">Actions</div>
                                        </div>

                                        {/* Table rows */}
                                        <div className="divide-y divide-slate-100 dark:divide-slate-700">
                                            {questionList.map((q) => {
                                                const isExpanded = expandedIds.includes(q.id);
                                                return (
                                                    <div key={q.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors duration-150">
                                                        {/* Main row */}
                                                        <div
                                                            className="grid grid-cols-[auto_1fr_auto_auto_auto_auto] gap-0 px-4 py-3 cursor-pointer select-none items-start"
                                                            onClick={() => toggleExpand(q.id)}
                                                        >
                                                            {/* Checkbox */}
                                                            <div className="w-7 flex items-center pt-0.5">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={selectedIds.includes(q.id)}
                                                                    onClick={(e) => e.stopPropagation()}
                                                                    onChange={(e) => {
                                                                        setSelectedIds(prev =>
                                                                            e.target.checked
                                                                                ? [...prev, q.id]
                                                                                : prev.filter(id => id !== q.id)
                                                                        );
                                                                    }}
                                                                    className="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-slate-100 cursor-pointer"
                                                                />
                                                            </div>

                                                            {/* Question text truncated + badges */}
                                                            <div className="min-w-0 pr-3">
                                                                <p className="text-sm font-medium text-slate-800 dark:text-slate-200 leading-snug truncate">
                                                                    {truncate(q.question_text)}
                                                                </p>
                                                                <div className="flex flex-wrap gap-1 mt-1">
                                                                    {q.is_duplicate && <DupBadge />}
                                                                    <AuditBadge status={q.audit_status} />
                                                                    {/* Category shown on small screens */}
                                                                    <span className="sm:hidden inline-flex items-center px-1.5 py-0.5 rounded text-xxs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                                        {q.category?.name}
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            {/* Category (hidden on small) */}
                                                            <div className="w-28 hidden sm:flex items-start justify-center pt-0.5">
                                                                <span className="text-center text-xxs font-medium text-slate-600 dark:text-slate-400 leading-tight">
                                                                    {q.category?.name}
                                                                </span>
                                                            </div>

                                                            {/* Audit status (hidden on small) */}
                                                            <div className="w-20 hidden md:flex items-start justify-center pt-0.5">
                                                                {!q.audit_status || q.audit_status === 'passed' ? (
                                                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xxs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400">
                                                                        <FontAwesomeIcon icon={faCheck} className="w-2 h-2" /> OK
                                                                    </span>
                                                                ) : (
                                                                    <AuditBadge status={q.audit_status} />
                                                                )}
                                                            </div>

                                                            {/* Date */}
                                                            <div className="w-16 hidden lg:flex items-start justify-center pt-0.5">
                                                                <span className="text-xxs text-slate-400 dark:text-slate-500">{fmtDate(q.created_at)}</span>
                                                            </div>

                                                            {/* Actions */}
                                                            <div className="w-24 flex items-start justify-end gap-2 pt-0.5" onClick={(e) => e.stopPropagation()}>
                                                                {q.audit_status && q.audit_status !== 'passed' && (
                                                                    <button
                                                                        onClick={() => handleSuggestFix(q)}
                                                                        disabled={fixingQuestionId === q.id}
                                                                        className="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition disabled:opacity-50"
                                                                        title="AI Fix"
                                                                    >
                                                                        <FontAwesomeIcon icon={fixingQuestionId === q.id ? faSpinner : faWandMagicSparkles} className={`w-3.5 h-3.5 ${fixingQuestionId === q.id ? 'animate-spin' : ''}`} />
                                                                    </button>
                                                                )}
                                                                <button
                                                                    onClick={() => startEdit(q)}
                                                                    className="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition"
                                                                    title="Edit"
                                                                >
                                                                    <FontAwesomeIcon icon={faPen} className="w-3.5 h-3.5" />
                                                                </button>
                                                                <button
                                                                    onClick={() => triggerDelete(q.id)}
                                                                    className="text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 transition"
                                                                    title="Delete"
                                                                >
                                                                    <FontAwesomeIcon icon={faTrash} className="w-3.5 h-3.5" />
                                                                </button>
                                                                <span className="text-slate-300 dark:text-slate-600">
                                                                    <FontAwesomeIcon icon={isExpanded ? faChevronUp : faChevronDown} className="w-3 h-3" />
                                                                </span>
                                                            </div>
                                                        </div>

                                                        {/* Expanded detail panel */}
                                                        <div className={`overflow-hidden transition-all duration-300 ease-in-out ${isExpanded ? 'max-h-[1200px] opacity-100' : 'max-h-0 opacity-0 pointer-events-none'}`}>
                                                            <div className="px-5 pb-4 pt-2 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40">
                                                                {/* Full question text */}
                                                                <p className="text-sm font-semibold text-slate-800 leading-relaxed mb-3.5 dark:text-slate-200 select-text whitespace-pre-line">
                                                                    {renderQuestionContent(q.question_text.replace(/\s*\(Variation ID:\s*\d+\)/gi, ''))}
                                                                </p>

                                                                {/* Options grid */}
                                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                                                                    {q.options.map((opt, idx) => (
                                                                        <div
                                                                            key={opt.id}
                                                                            className={`p-2.5 text-xs border rounded-lg flex items-center gap-2 ${opt.is_correct
                                                                                ? 'border-emerald-400 bg-emerald-50/40 font-semibold dark:border-emerald-500/30 dark:bg-emerald-950/20 dark:text-emerald-300'
                                                                                : 'border-slate-200 dark:border-slate-700 dark:text-slate-400'}`}
                                                                        >
                                                                            <span className={`w-4 h-4 rounded text-xxs font-extrabold flex items-center justify-center shrink-0 ${opt.is_correct ? 'bg-emerald-600 text-white dark:bg-emerald-500' : 'bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-500'}`}>
                                                                                {String.fromCharCode(65 + idx)}
                                                                            </span>
                                                                            <span className="flex-1 min-w-0">{renderQuestionContent(opt.option_text)}</span>
                                                                            {opt.is_correct && <FontAwesomeIcon icon={faCheck} className="ml-auto text-emerald-600 dark:text-emerald-400 w-3 h-3 shrink-0" />}
                                                                        </div>
                                                                    ))}
                                                                </div>

                                                                {/* Audit error block */}
                                                                {q.audit_status && q.audit_status !== 'passed' && q.audit_error && (
                                                                    <div className="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xxs text-red-800 dark:bg-red-950/20 dark:border-red-900/40 dark:text-red-300 mb-3 flex items-center gap-1.5">
                                                                        <FontAwesomeIcon icon={faExclamationTriangle} className="text-red-600 w-3 h-3 shrink-0" />
                                                                        <span><strong>Audit Warning:</strong> {q.audit_error}</span>
                                                                    </div>
                                                                )}

                                                                {/* Explanation */}
                                                                {q.explanation && (
                                                                    <div className="bg-white border border-slate-200 rounded-lg px-3 py-2 text-xxs text-slate-500 dark:bg-slate-900/30 dark:border-slate-700 dark:text-slate-400">
                                                                        <span className="font-bold text-slate-700 dark:text-slate-300 block mb-0.5">Explanation:</span>
                                                                        {q.explanation}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        {/* Pagination */}
                                        <div className="px-4 pb-4">
                                            <Pagination meta={paginatorMeta} filters={filters} onNavigate={navigate} />
                                        </div>
                                    </>
                                )}
                            </div>

                        </div>{/* end right column */}
                    </div>
                </div>
            </div>

            {/* ── Modals ────────────────────────────────────────────────── */}
            <ConfirmationModal
                isOpen={deleteModalOpen}
                title="Delete Question"
                message="Are you sure you want to permanently delete this exam question? This action cannot be undone."
                confirmLabel="Delete"
                cancelLabel="Cancel"
                type="danger"
                onConfirm={confirmDelete}
                onClose={() => setDeleteModalOpen(false)}
            />

            <ConfirmationModal
                isOpen={cleanModalOpen}
                title="Auto-Clean Duplicate Questions"
                message="Are you sure you want to merge duplicate questions? This will permanently delete all duplicates, keeping only the oldest record for each. This action cannot be undone."
                confirmLabel="Clean & Merge"
                cancelLabel="Cancel"
                type="danger"
                onConfirm={handleCleanDuplicates}
                onClose={() => setCleanModalOpen(false)}
            />

            <ConfirmationModal
                isOpen={bulkDeleteModalOpen}
                title="Delete Selected Questions"
                message={`Are you sure you want to permanently delete the ${selectedIds.length} selected question(s)? This action cannot be undone.`}
                confirmLabel="Delete Selected"
                cancelLabel="Cancel"
                type="danger"
                onConfirm={handleBulkDelete}
                onClose={() => setBulkDeleteModalOpen(false)}
            />

            {/* ── Floating Bulk Actions Bar ─────────────────────────────── */}
            {selectedIds.length > 0 && (
                <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900/90 dark:bg-slate-950/95 backdrop-blur-md border border-slate-800 text-white rounded-full px-6 py-3.5 shadow-2xl flex items-center gap-5 select-none">
                    <div className="flex items-center gap-2">
                        <span className="flex h-2 w-2 relative">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75" />
                            <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-500" />
                        </span>
                        <span className="text-xs font-bold tracking-wide">{selectedIds.length} selected</span>
                    </div>
                    <div className="h-4 w-px bg-slate-700" />
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setBulkDeleteModalOpen(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-rose-600 hover:bg-rose-700 text-xs font-extrabold text-white transition shadow-sm cursor-pointer"
                        >
                            <FontAwesomeIcon icon={faTrash} className="w-3 h-3" />
                            Delete Selected
                        </button>
                        <button
                            onClick={() => setSelectedIds([])}
                            className="px-3.5 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
