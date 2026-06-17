import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
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
    faChevronUp
} from '@fortawesome/free-solid-svg-icons';

export default function Questions({ questions, categories, aiUsage }) {

    const [editingQuestion, setEditingQuestion] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [questionToDelete, setQuestionToDelete] = useState(null);
    const [showDuplicatesOnly, setShowDuplicatesOnly] = useState(false);
    const [cleanModalOpen, setCleanModalOpen] = useState(false);
    const [showAuditIssuesOnly, setShowAuditIssuesOnly] = useState(false);
    const [auditing, setAuditing] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkDeleteModalOpen, setBulkDeleteModalOpen] = useState(false);
    const [expandedIds, setExpandedIds] = useState([]);
    const [fixingQuestionId, setFixingQuestionId] = useState(null);

    const toggleExpand = (id) => {
        setExpandedIds(prev =>
            prev.includes(id) ? prev.filter(item => item !== id) : [...prev, id]
        );
    };

    const handleExpandAll = () => {
        setExpandedIds(filteredQuestions.map(q => q.id));
    };

    const handleCollapseAll = () => {
        setExpandedIds([]);
    };

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
                        is_correct: !!opt.is_correct
                    }))
                });

                // Scroll the add/edit form container into view
                const formEl = document.getElementById('form_category');
                if (formEl) {
                    formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            })
            .catch(error => {
                console.error("AI suggested fix failed:", error);
                alert(error.response?.data?.error || "Failed to fetch AI suggested corrections. Please verify configuration or logs.");
            })
            .finally(() => {
                setFixingQuestionId(null);
            });
    };

    // Inertia Form hook for Add/Edit
    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        exam_category_id: '',
        question_text: '',
        explanation: '',
        options: [
            { option_text: '', is_correct: true },
            { option_text: '', is_correct: false },
            { option_text: '', is_correct: false },
            { option_text: '', is_correct: false },
        ]
    });

    // Inertia Form hook for AI generation
    const aiForm = useForm({
        exam_category_id: '',
        level: 'professional',
        count: 1,
    });

    const handleOptionTextChange = (index, value) => {
        const newOptions = [...data.options];
        newOptions[index].option_text = value;
        setData('options', newOptions);
    };

    const handleOptionCorrectToggle = (index) => {
        const newOptions = data.options.map((opt, idx) => ({
            ...opt,
            is_correct: idx === index // Ensure only one correct option (radio button behavior)
        }));
        setData('options', newOptions);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        if (editingQuestion) {
            put(route('admin.questions.update', editingQuestion.id), {
                onSuccess: () => {
                    cancelEdit();
                }
            });
        } else {
            post(route('admin.questions.store'), {
                onSuccess: () => {
                    resetForm();
                }
            });
        }
    };

    const handleAILevelChange = (newLevel) => {
        if (aiForm.data.exam_category_id === 'all') {
            aiForm.setData({
                ...aiForm.data,
                level: newLevel
            });
            return;
        }

        const selectedCat = categories.find(c => c.id === parseInt(aiForm.data.exam_category_id));
        const keepCategory = selectedCat && (selectedCat.level === 'both' || selectedCat.level === newLevel);
        
        aiForm.setData({
            ...aiForm.data,
            level: newLevel,
            exam_category_id: keepCategory ? aiForm.data.exam_category_id : ''
        });
    };

    const handleAIGenerate = (e) => {
        e.preventDefault();
        aiForm.post(route('admin.questions.generateAI'), {
            onSuccess: () => {
                aiForm.reset('exam_category_id');
            }
        });
    };

    const startEdit = (question) => {
        setEditingQuestion(question);
        clearErrors();
        
        // Map options to fillable schema format
        const formOptions = question.options.map(opt => ({
            option_text: opt.option_text,
            is_correct: !!opt.is_correct
        }));

        // Fill up to 4 options if it's less
        while (formOptions.length < 4) {
            formOptions.push({ option_text: '', is_correct: false });
        }

        setData({
            exam_category_id: question.exam_category_id,
            question_text: question.question_text.replace(/\s*\(Variation ID:\s*\d+\)/gi, ''),
            explanation: question.explanation || '',
            options: formOptions
        });
    };

    const cancelEdit = () => {
        setEditingQuestion(null);
        resetForm();
    };

    const resetForm = () => {
        clearErrors();
        reset();
    };

    const triggerDelete = (id) => {
        setQuestionToDelete(id);
        setDeleteModalOpen(true);
    };

    const confirmDelete = () => {
        if (questionToDelete) {
            router.delete(route('admin.questions.destroy', questionToDelete), {
                onSuccess: () => {
                    setQuestionToDelete(null);
                }
            });
        }
    };

    const handleCleanDuplicates = () => {
        router.post(route('admin.questions.cleanDuplicates'), {}, {
            onSuccess: () => {
                setCleanModalOpen(false);
            }
        });
    };

    const handleRunAudit = () => {
        setAuditing(true);
        router.post(route('admin.questions.runAudit'), {}, {
            onFinish: () => {
                setAuditing(false);
            }
        });
    };

    const handleToggleSelectAll = () => {
        if (selectedIds.length === filteredQuestions.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(filteredQuestions.map(q => q.id));
        }
    };

    const handleBulkDelete = () => {
        router.delete(route('admin.questions.bulkDestroy'), {
            data: { ids: selectedIds },
            onSuccess: () => {
                setSelectedIds([]);
                setBulkDeleteModalOpen(false);
            }
        });
    };


    // Filter questions based on search term, category filter, duplicates-only, and audit-issues-only
    const filteredQuestions = questions.filter(q => {
        const matchesSearch = q.question_text.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesCategory = categoryFilter === '' || q.exam_category_id === parseInt(categoryFilter);
        const matchesDuplicates = !showDuplicatesOnly || q.is_duplicate;
        const matchesAuditIssues = !showAuditIssuesOnly || (q.audit_status && q.audit_status !== 'passed');
        return matchesSearch && matchesCategory && matchesDuplicates && matchesAuditIssues;
    });

    const filteredAICategories = categories.filter(cat => {
        return cat.level === 'both' || cat.level === aiForm.data.level;
    });

    const duplicateQuestionsCount = questions.filter(q => q.is_duplicate).length;
    const auditIssuesQuestionsCount = questions.filter(q => q.audit_status && q.audit_status !== 'passed').length;

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
                            Total: {questions.length}
                        </span>
                        {duplicateQuestionsCount > 0 && (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xxs font-bold bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60 uppercase tracking-wider animate-pulse">
                                Duplicates: {duplicateQuestionsCount}
                            </span>
                        )}
                        {auditIssuesQuestionsCount > 0 && (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xxs font-bold bg-rose-50 text-rose-800 dark:bg-rose-900/30 dark:text-rose-350 border border-rose-200 dark:border-rose-900/60 uppercase tracking-wider animate-pulse">
                                Flagged: {auditIssuesQuestionsCount}
                            </span>
                        )}
                        {aiUsage && (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xxs font-bold bg-indigo-50 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-900/60 uppercase tracking-wider">
                                AI: {aiUsage.count}/{aiUsage.limit}
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
                        
                        {/* Left Column: Form to Add/Edit Question */}
                        <div className="lg:col-span-1 space-y-6">
                            
                            {/* AI Generation Widget */}
                            {!editingQuestion && (
                                <div className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700">
                                    <h3 className="text-base font-bold text-slate-850 dark:text-slate-100 mb-2 flex items-center gap-2">
                                        <FontAwesomeIcon icon={faWandMagicSparkles} className="text-slate-700 dark:text-slate-300" />
                                        <span>AI Question Generator</span>
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
                                        Instantly generate high-quality CSE questions matching category, level, and quantity.
                                    </p>

                                    {/* Warnings if API key is invalid/missing or quota exceeded */}
                                    {aiUsage && !aiUsage.hasApiKey && (
                                        <div className="mb-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60">
                                            <div className="flex gap-2">
                                                <FontAwesomeIcon icon={faExclamationTriangle} className="mt-0.5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                                                <div>
                                                    <span className="font-semibold block mb-0.5">Local Mock Fallback Mode Active</span>
                                                    Your <code>AI_API_KEY</code> in <code>.env</code> is missing or using a default placeholder. The system is falling back to local mock templates, which will result in duplicate/repeating questions.
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {aiUsage && aiUsage.quotaExceeded && (
                                        <div className="mb-4 rounded-lg bg-red-50 p-3 text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300 border border-red-200 dark:border-red-900/60">
                                            <div className="flex gap-2">
                                                <FontAwesomeIcon icon={faExclamationTriangle} className="mt-0.5 text-red-650 dark:text-red-400 flex-shrink-0" />
                                                <div>
                                                    <span className="font-semibold block mb-0.5">Gemini API Quota Exceeded (429)</span>
                                                    Your Gemini API free tier limit or billing quota has been exceeded. The system has fallen back to local mock templates, which will result in duplicate/repeating questions.
                                                    <button 
                                                        onClick={() => router.post(route('admin.questions.resetAIUsage'))}
                                                        className="mt-2 text-xxs font-semibold underline text-red-700 hover:text-red-900 dark:text-red-400 dark:hover:text-red-350 block text-left"
                                                    >
                                                        Clear Quota Error & Reset Stats
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {aiUsage && !aiUsage.quotaExceeded && aiUsage.lastError && (
                                        <div className="mb-4 rounded-lg bg-red-50 p-3 text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300 border border-red-200 dark:border-red-900/60">
                                            <div className="flex gap-2">
                                                <FontAwesomeIcon icon={faExclamationTriangle} className="mt-0.5 text-red-650 dark:text-red-400 flex-shrink-0" />
                                                <div>
                                                    <span className="font-semibold block mb-0.5">AI Generation Error</span>
                                                    The last API call failed with error: <em className="break-all">{aiUsage.lastError}</em>. The system fell back to local mock templates.
                                                    <button 
                                                        onClick={() => router.post(route('admin.questions.resetAIUsage'))}
                                                        className="mt-2 text-xxs font-semibold underline text-red-700 hover:text-red-900 dark:text-red-400 dark:hover:text-red-350 block text-left"
                                                    >
                                                        Clear Error Message
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                    
                                    <form onSubmit={handleAIGenerate} className="space-y-4">
                                        <div>
                                            <label htmlFor="ai_level" className="block text-xxs font-bold text-slate-700 dark:text-slate-355 uppercase tracking-wider mb-1">
                                                Level
                                            </label>
                                            <select
                                                id="ai_level"
                                                value={aiForm.data.level}
                                                onChange={(e) => handleAILevelChange(e.target.value)}
                                                className="block w-full rounded-lg border-slate-250 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                required
                                            >
                                                <option value="professional">Professional</option>
                                                <option value="sub_professional">Sub-Professional</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label htmlFor="ai_category" className="block text-xxs font-bold text-slate-700 dark:text-slate-355 uppercase tracking-wider mb-1">
                                                Category
                                            </label>
                                            <select
                                                id="ai_category"
                                                value={aiForm.data.exam_category_id}
                                                onChange={(e) => aiForm.setData('exam_category_id', e.target.value)}
                                                className="block w-full rounded-lg border-slate-250 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                required
                                            >
                                                <option value="">Select Category</option>
                                                <option value="all">All Categories Combined</option>
                                                {filteredAICategories.map((cat) => (
                                                    <option key={cat.id} value={cat.id}>
                                                        {cat.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label htmlFor="ai_count" className="block text-xxs font-bold text-slate-700 dark:text-slate-350 uppercase tracking-wider mb-1">
                                                Quantity
                                            </label>
                                            <select
                                                id="ai_count"
                                                value={aiForm.data.count}
                                                onChange={(e) => aiForm.setData('count', e.target.value)}
                                                className="block w-full rounded-lg border-slate-250 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                required
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

                                        <button
                                            type="submit"
                                            disabled={aiForm.processing}
                                            className="w-full inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                                        >
                                            {aiForm.processing ? (
                                                <>
                                                    <FontAwesomeIcon icon={faSpinner} className="animate-spin mr-2" />
                                                    Generating Questions...
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

                            <div className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700">
                                <h3 className="text-base font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
                                    <FontAwesomeIcon icon={editingQuestion ? faPen : faPlus} className="text-slate-700 dark:text-slate-300" />
                                    {editingQuestion ? 'Edit Question' : 'Add New Question'}
                                </h3>

                                <form onSubmit={handleSubmit} className="space-y-4">
                                    {/* Category Select */}
                                    <div>
                                        <label htmlFor="form_category" className="block text-xs font-bold text-slate-750 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                            Exam Category
                                        </label>
                                        <select
                                            id="form_category"
                                            value={data.exam_category_id}
                                            onChange={(e) => setData('exam_category_id', e.target.value)}
                                            className="block w-full rounded-lg border-slate-250 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
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

                                    {/* Question Text */}
                                    <div>
                                        <label htmlFor="form_text" className="block text-xs font-bold text-slate-750 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                            Question Text
                                        </label>
                                        <textarea
                                            id="form_text"
                                            rows="4"
                                            value={data.question_text}
                                            onChange={(e) => setData('question_text', e.target.value)}
                                            placeholder="Enter multiple choice question..."
                                            className="block w-full rounded-lg border-slate-250 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                            required
                                        ></textarea>
                                        {errors.question_text && <p className="text-xs text-red-600 mt-1">{errors.question_text}</p>}
                                    </div>

                                    {/* Options (A, B, C, D) */}
                                    <div className="space-y-3 pt-2">
                                        <span className="block text-xs font-bold text-slate-755 dark:text-slate-300 uppercase tracking-wider">
                                            Answer Options (Check the correct one)
                                        </span>
                                        {errors.options && <p className="text-xs text-red-650 font-medium">{errors.options}</p>}
                                        
                                        {data.options.map((option, idx) => {
                                            const label = String.fromCharCode(65 + idx);
                                            return (
                                                <div key={idx} className="flex items-center gap-3">
                                                    <input
                                                        type="radio"
                                                        name="correct-option"
                                                        checked={option.is_correct}
                                                        onChange={() => handleOptionCorrectToggle(idx)}
                                                        className="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:checked:bg-slate-100"
                                                        title="Mark as correct option"
                                                    />
                                                    <div className="flex-1 relative">
                                                        <span className="absolute left-3 top-2.5 text-xs font-bold text-slate-400">
                                                            {label}
                                                        </span>
                                                        <input
                                                            type="text"
                                                            value={option.option_text}
                                                            onChange={(e) => handleOptionTextChange(idx, e.target.value)}
                                                            placeholder={`Option ${label}`}
                                                            className="block w-full rounded-lg border-slate-250 bg-white pl-8 pr-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Explanation */}
                                    <div>
                                        <label htmlFor="form_explanation" className="block text-xs font-bold text-slate-750 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                            Explanatory Details (Review Mode)
                                        </label>
                                        <textarea
                                            id="form_explanation"
                                            rows="3"
                                            value={data.explanation}
                                            onChange={(e) => setData('explanation', e.target.value)}
                                            placeholder="Provide reasoning/calculations for the correct choice..."
                                            className="block w-full rounded-lg border-slate-250 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        ></textarea>
                                        {errors.explanation && <p className="text-xs text-red-600 mt-1">{errors.explanation}</p>}
                                    </div>

                                    {/* Actions */}
                                    <div className="flex gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="flex-1 inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                                        >
                                            {processing ? (
                                                <FontAwesomeIcon icon={faSpinner} className="animate-spin mr-1.5" />
                                            ) : (
                                                <FontAwesomeIcon icon={faSave} className="mr-1.5" />
                                            )}
                                            {editingQuestion ? 'Update' : 'Save'}
                                        </button>
                                        {editingQuestion && (
                                            <button
                                                type="button"
                                                onClick={cancelEdit}
                                                className="inline-flex items-center justify-center rounded-lg border border-slate-250 bg-white px-3 py-2 text-sm font-semibold text-slate-705 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition"
                                            >
                                                <FontAwesomeIcon icon={faBan} className="mr-1.5" />
                                                Cancel
                                            </button>
                                        )}
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Right Column: Database List of Questions */}
                        <div className="lg:col-span-2 space-y-4">
                            
                            {/* Search and Filters panel */}
                            <div className="bg-white border border-slate-200 rounded-xl p-4 dark:bg-slate-800 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center gap-4">
                                <div className="flex-1 relative">
                                    <input
                                        type="text"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        placeholder="Search questions..."
                                        className="block w-full rounded-lg border-slate-250 bg-white pl-4 pr-10 py-2 text-sm text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                    />
                                    <FontAwesomeIcon icon={faSearch} className="absolute right-3.5 top-3 text-slate-405 dark:text-slate-500" />
                                </div>
                                <div className="w-full sm:w-48 relative">
                                    <select
                                        value={categoryFilter}
                                        onChange={(e) => setCategoryFilter(e.target.value)}
                                        className="block w-full rounded-lg border-slate-250 bg-white pl-8 pr-3 py-2 text-sm text-slate-850 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                    >
                                        <option value="">All Categories</option>
                                        {categories.map((cat) => (
                                            <option key={cat.id} value={cat.id}>
                                                {cat.name}
                                            </option>
                                        ))}
                                    </select>
                                    <FontAwesomeIcon icon={faFilter} className="absolute left-3 top-3 text-slate-400" />
                                </div>
                                {duplicateQuestionsCount > 0 && (
                                    <div className="flex items-center gap-2 select-none shrink-0">
                                        <input
                                            type="checkbox"
                                            id="duplicates_filter"
                                            checked={showDuplicatesOnly}
                                            onChange={(e) => setShowDuplicatesOnly(e.target.checked)}
                                            className="h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:checked:bg-slate-100"
                                        />
                                        <label htmlFor="duplicates_filter" className="text-xs font-semibold text-slate-600 dark:text-slate-400 cursor-pointer">
                                            Duplicates Only
                                        </label>
                                    </div>
                                )}
                                <button
                                    onClick={handleRunAudit}
                                    disabled={auditing}
                                    className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition shrink-0"
                                    title="Audit all questions structurally and factually using AI"
                                >
                                    {auditing ? (
                                        <>
                                            <FontAwesomeIcon icon={faSpinner} className="animate-spin mr-1.5" />
                                            Auditing...
                                        </>
                                    ) : (
                                        <>
                                            <FontAwesomeIcon icon={faWandMagicSparkles} className="mr-1.5 text-indigo-500" />
                                            Run Integrity Audit
                                        </>
                                    )}
                                </button>
                            </div>

                            {/* Duplicate Warning Banner */}
                            {duplicateQuestionsCount > 0 && (
                                <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 dark:bg-amber-950/20 dark:border-amber-900/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm">
                                    <div className="flex items-start gap-3">
                                        <span className="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 shrink-0">
                                            <FontAwesomeIcon icon={faExclamationTriangle} className="w-4 h-4" />
                                        </span>
                                        <div>
                                            <h4 className="text-sm font-bold text-amber-900 dark:text-amber-300">
                                                Duplicate Questions Detected
                                            </h4>
                                            <p className="text-xs text-amber-700 dark:text-amber-450 mt-0.5 leading-relaxed">
                                                There are <strong>{duplicateQuestionsCount}</strong> duplicate questions in the database. Cleaning them will merge duplicates and retain the oldest entry.
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

                            {/* Integrity Audit Warning Banner */}
                            {auditIssuesQuestionsCount > 0 && (
                                <div className="bg-red-50 border border-red-200 rounded-xl p-4 dark:bg-red-950/20 dark:border-red-900/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm">
                                    <div className="flex items-start gap-3">
                                        <span className="flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 shrink-0">
                                            <FontAwesomeIcon icon={faExclamationTriangle} className="w-4 h-4" />
                                        </span>
                                        <div>
                                            <h4 className="text-sm font-bold text-red-900 dark:text-red-300">
                                                Integrity Issues Flagged
                                            </h4>
                                            <p className="text-xs text-red-700 dark:text-red-400 mt-0.5 leading-relaxed">
                                                The audit scanned the database and flagged <strong>{auditIssuesQuestionsCount}</strong> questions with structural or factual errors. Please review and correct them.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 select-none shrink-0">
                                        <input
                                            type="checkbox"
                                            id="audit_filter"
                                            checked={showAuditIssuesOnly}
                                            onChange={(e) => setShowAuditIssuesOnly(e.target.checked)}
                                            className="h-4 w-4 rounded border-red-300 text-red-600 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:checked:bg-red-500"
                                        />
                                        <label htmlFor="audit_filter" className="text-xs font-bold text-red-700 dark:text-red-400 cursor-pointer">
                                            Filter Flagged Only
                                        </label>
                                    </div>
                                </div>
                            )}

                            {/* Questions Count indicator */}
                            <div className="flex items-center justify-between pl-1 pr-2">
                                <p className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Database: {filteredQuestions.length} Questions found {searchTerm || categoryFilter ? '(filtered)' : ''}
                                </p>
                                {filteredQuestions.length > 0 && (
                                    <div className="flex items-center gap-3 select-none">
                                        <button
                                            type="button"
                                            onClick={handleToggleSelectAll}
                                            className="text-xxs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 hover:underline cursor-pointer"
                                        >
                                            {selectedIds.length === filteredQuestions.length ? 'Deselect All' : 'Select All Filtered'}
                                        </button>
                                        <span className="text-slate-300 dark:text-slate-600 text-xxs">|</span>
                                        <button
                                            type="button"
                                            onClick={expandedIds.length === filteredQuestions.length ? handleCollapseAll : handleExpandAll}
                                            className="text-xxs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 hover:underline cursor-pointer"
                                        >
                                            {expandedIds.length === filteredQuestions.length ? 'Collapse All' : 'Expand All'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* List of Questions */}
                            <div className="space-y-4 max-h-[calc(100vh-320px)] overflow-y-auto pr-2">
                                {filteredQuestions.length === 0 ? (
                                    <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400">
                                        No questions in database matching filters. Use the forms on the left to create or generate questions.
                                    </div>
                                ) : (
                                    filteredQuestions.map((q) => {
                                        const isExpanded = expandedIds.includes(q.id);
                                        return (
                                            <div 
                                                key={q.id}
                                                className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700 hover:shadow-md transition-shadow duration-200"
                                            >
                                                {/* Header & Truncated Question Row (Clickable to Toggle) */}
                                                <div 
                                                    onClick={() => toggleExpand(q.id)}
                                                    className="cursor-pointer select-none"
                                                >
                                                    <div className="flex justify-between items-start pb-2.5">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedIds.includes(q.id)}
                                                                onClick={(e) => e.stopPropagation()}
                                                                onChange={(e) => {
                                                                    if (e.target.checked) {
                                                                        setSelectedIds(prev => [...prev, q.id]);
                                                                    } else {
                                                                        setSelectedIds(prev => prev.filter(id => id !== q.id));
                                                                    }
                                                                }}
                                                                className="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:checked:bg-slate-100 mr-2 cursor-pointer shrink-0"
                                                                title="Select question"
                                                            />
                                                            <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                                {q.category.name}
                                                            </span>
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xxs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 uppercase">
                                                                {q.category.level === 'both' ? 'Prof & Sub-Prof' : q.category.level}
                                                            </span>
                                                            {q.is_duplicate && (
                                                                <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xxs font-bold bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-405 uppercase tracking-wider animate-pulse">
                                                                    <FontAwesomeIcon icon={faExclamationTriangle} className="w-2.5 h-2.5" />
                                                                    Duplicate
                                                                </span>
                                                            )}
                                                            {q.audit_status && q.audit_status !== 'passed' && (
                                                                <span 
                                                                    className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xxs font-bold bg-red-50 text-red-800 dark:bg-red-950/30 dark:text-red-400 uppercase tracking-wider animate-pulse cursor-help"
                                                                    title={q.audit_error || 'Correctness verification failed'}
                                                                >
                                                                    <FontAwesomeIcon icon={faExclamationTriangle} className="w-2.5 h-2.5 text-red-600" />
                                                                    {q.audit_status === 'failed_structure' ? 'Structural Mismatch' : 'Factual Warning'}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-3 shrink-0">
                                                            {q.audit_status && q.audit_status !== 'passed' && (
                                                                <>
                                                                    <button
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            handleSuggestFix(q);
                                                                        }}
                                                                        disabled={fixingQuestionId === q.id}
                                                                        className="text-xs font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition flex items-center gap-1 disabled:opacity-50 cursor-pointer"
                                                                        title="Get AI suggested fixes for audit finding"
                                                                    >
                                                                        <FontAwesomeIcon icon={fixingQuestionId === q.id ? faSpinner : faWandMagicSparkles} className={fixingQuestionId === q.id ? "animate-spin w-3 h-3" : "w-3 h-3"} />
                                                                        {fixingQuestionId === q.id ? 'Fixing...' : 'Fix'}
                                                                    </button>
                                                                    <span className="text-slate-200 dark:text-slate-700">|</span>
                                                                </>
                                                            )}
                                                            <button
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    startEdit(q);
                                                                }}
                                                                className="text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 transition flex items-center gap-1"
                                                            >
                                                                <FontAwesomeIcon icon={faPen} className="w-3 h-3" />
                                                                Edit
                                                            </button>
                                                            <span className="text-slate-200 dark:text-slate-700">|</span>
                                                            <button
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    triggerDelete(q.id);
                                                                }}
                                                                className="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 transition flex items-center gap-1"
                                                            >
                                                                <FontAwesomeIcon icon={faTrash} className="w-3 h-3" />
                                                                Delete
                                                            </button>
                                                            <span className="text-slate-200 dark:text-slate-700">|</span>
                                                            <div className="p-0.5 text-slate-400 hover:text-slate-800 dark:text-slate-500 dark:hover:text-slate-300 transition-colors">
                                                                <FontAwesomeIcon icon={isExpanded ? faChevronUp : faChevronDown} className="w-3.5 h-3.5" />
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Question Text Snippet (Visible when collapsed) */}
                                                    {!isExpanded && (
                                                        <p className="text-sm font-semibold text-slate-700 dark:text-slate-300 truncate pr-4 leading-normal mt-1 border-t border-slate-50 pt-2 dark:border-slate-700/50">
                                                            {q.question_text.replace(/\s*\(Variation ID:\s*\d+\)/gi, '')}
                                                        </p>
                                                    )}
                                                </div>

                                                {/* Expanded Content with height transition */}
                                                <div 
                                                    className={`transition-all duration-300 ease-in-out overflow-hidden ${isExpanded ? 'max-h-[1200px] opacity-100 mt-3 border-t border-slate-100 pt-3 dark:border-slate-700' : 'max-h-0 opacity-0 pointer-events-none'}`}
                                                >
                                                    {/* Question Text */}
                                                    <p className="text-sm font-semibold text-slate-800 leading-relaxed mb-4 dark:text-slate-200 select-text whitespace-pre-line">
                                                        {renderQuestionContent(q.question_text.replace(/\s*\(Variation ID:\s*\d+\)/gi, ''))}
                                                    </p>

                                                    {/* Options Grid */}
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                                                        {q.options.map((opt, idx) => (
                                                            <div 
                                                                key={opt.id} 
                                                                className={`p-2.5 text-xs border rounded-lg flex items-center gap-2 ${opt.is_correct ? 'border-emerald-500 bg-emerald-50/30 font-semibold dark:border-emerald-500/30 dark:bg-emerald-950/20 dark:text-emerald-300' : 'border-slate-200 dark:border-slate-700 dark:text-slate-400'}`}
                                                            >
                                                                <span className={`w-4 h-4 rounded text-xxs font-extrabold flex items-center justify-center shrink-0 ${opt.is_correct ? 'bg-emerald-600 text-white dark:bg-emerald-500' : 'bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-500'}`}>
                                                                     {String.fromCharCode(65 + idx)}
                                                                </span>
                                                                <span className="flex-1 min-w-0">{renderQuestionContent(opt.option_text)}</span>
                                                                {opt.is_correct && (
                                                                    <FontAwesomeIcon icon={faCheck} className="ml-auto text-emerald-600 dark:text-emerald-400 w-3 h-3 shrink-0" />
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>

                                                    {/* Audit Error Warning Block */}
                                                    {q.audit_status && q.audit_status !== 'passed' && q.audit_error && (
                                                        <div className="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xxs text-red-800 dark:bg-red-950/20 dark:border-red-900/40 dark:text-red-300 mb-3 flex items-center gap-1.5">
                                                            <FontAwesomeIcon icon={faExclamationTriangle} className="text-red-600 w-3 h-3 shrink-0" />
                                                            <span><strong>Audit Warning:</strong> {q.audit_error}</span>
                                                        </div>
                                                    )}

                                                    {/* Explanation */}
                                                    {q.explanation && (
                                                        <div className="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xxs text-slate-500 dark:bg-slate-900/30 dark:border-slate-700 dark:text-slate-405">
                                                            <span className="font-bold text-slate-700 dark:text-slate-350 block mb-0.5">Explanation:</span>
                                                            {q.explanation}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>

                        </div>

                    </div>

                </div>
            </div>

            {/* Reusable Confirmation Modal */}
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
                message="Are you sure you want to merge duplicate questions? This will permanently delete all duplicate questions, keeping only the oldest record for each. This action cannot be undone."
                confirmLabel="Clean & Merge"
                cancelLabel="Cancel"
                type="danger"
                onConfirm={handleCleanDuplicates}
                onClose={() => setCleanModalOpen(false)}
            />

            <ConfirmationModal
                isOpen={bulkDeleteModalOpen}
                title="Delete Selected Questions"
                message={`Are you sure you want to permanently delete the ${selectedIds.length} selected question(s)? This action will remove them from the database and cannot be undone.`}
                confirmLabel="Delete Selected"
                cancelLabel="Cancel"
                type="danger"
                onConfirm={handleBulkDelete}
                onClose={() => setBulkDeleteModalOpen(false)}
            />

            {/* Floating Bulk Actions Bar */}
            {selectedIds.length > 0 && (
                <div className="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50 bg-slate-900/90 dark:bg-slate-950/95 backdrop-blur-md border border-slate-800 text-white rounded-full px-6 py-3.5 shadow-2xl flex items-center gap-6 select-none transition-all duration-350">
                    <div className="flex items-center gap-2 shrink-0">
                        <span className="flex h-2 w-2 relative">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                        </span>
                        <span className="text-xs font-bold tracking-wide">
                            {selectedIds.length} question(s) selected
                        </span>
                    </div>
                    <div className="h-4 w-px bg-slate-800"></div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setBulkDeleteModalOpen(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-rose-600 hover:bg-rose-700 text-xs font-extrabold text-white transition shadow-sm cursor-pointer"
                        >
                            <FontAwesomeIcon icon={faTrash} className="w-3 h-3" />
                            Delete Selected
                        </button>
                        <button
                            type="button"
                            onClick={() => setSelectedIds([])}
                            className="px-3.5 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-350 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
