import React, { useState, useEffect } from "react";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical, Trash2, CheckCircle2, Circle } from "lucide-react";
import { Todo } from "../types";

interface TodoItemProps {
	todo: Todo;
	loading: boolean;
	onToggle: (id: string) => void;
	onDelete: (id: string) => void;
	onUpdate: (id: any, data: { title: string; description: string }) => void;
}

export const TodoItem: React.FC<TodoItemProps> = ({
	todo,
	loading,
	onUpdate,
	onToggle,
	onDelete,
}) => {
	const [isEditing, setIsEditing] = useState(false);
	const [editTitle, setEditTitle] = useState(todo.title);
	const [editDesc, setEditDesc] = useState(todo.description);

	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable({ id: todo.id });

	const style = {
		transform: CSS.Transform.toString(transform),
		transition,
		zIndex: isDragging ? 10 : 1,
	};

	useEffect(() => {
		setEditTitle(todo.title);
		setEditDesc(todo.description);
	}, [todo]);

	const handleDoubleClick = () => {
		if (!todo.completed) {
			setIsEditing(true);
		}
	};

	const handleSave = () => {
		if (!editTitle.trim()) {
			setEditTitle(todo.title);
			setIsEditing(false);
			return;
		}

		if (editTitle !== todo.title || editDesc !== todo.description) {
			onUpdate(todo.id, {
				title: editTitle,
				description: editDesc,
			});

			setIsEditing(false);
		}
	};

	const handleKeyDown = (e: React.KeyboardEvent) => {
		if (e.key === "Enter" && !e.shiftKey) {
			if (e.target.nodeName == "INPUT") {
				e.preventDefault();
				handleSave();
			}
		}
		if (e.key === "Escape") {
			setEditTitle(todo.title);
			setEditDesc(todo.description);
			setIsEditing(false);
		}
	};

	return (
		<div
			ref={setNodeRef}
			style={style}
			className={`group flex items-center gap-3 p-4 bg-white border border-slate-200 rounded-xl shadow-sm mb-3 transition-all duration-200 ${
				isDragging
					? "shadow-lg ring-2 ring-primary scale-[1.02] opacity-90"
					: "hover:border-red-200"
			} ${todo.completed ? "bg-slate-50" : ""}`}>
			<div
				{...attributes}
				{...listeners}
				className="cursor-grab active:cursor-grabbing text-slate-400 hover:text-red-600 p-1">
				<GripVertical size={20} />
			</div>

			{!isEditing ? (
				<button
					onClick={() => onToggle(todo.id)}
					className={`flex-shrink-0 transition-colors ${
						todo.completed
							? "text-green-500"
							: "text-slate-300 hover:text-primary"
					}`}>
					{todo.completed ? <CheckCircle2 size={24} /> : <Circle size={24} />}
				</button>
			) : (
				""
			)}

			<div className="flex-grow min-w-0" onDoubleClick={handleDoubleClick}>
				{isEditing ? (
					<div className="flex flex-col gap-2 animate-in fade-in duration-200">
						<input
							autoFocus
							type="text"
							value={editTitle}
							onChange={(e) => setEditTitle(e.target.value)}
							onBlur={handleSave}
							onKeyDown={handleKeyDown}
							className="w-full p-1 px-2 -ml-2 font-semibold text-sm sm:text-base text-slate-800 bg-white border border-indigo-300 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500"
						/>
						<textarea
							value={editDesc}
							onChange={(e) => setEditDesc(e.target.value)}
							onBlur={handleSave}
							onKeyDown={handleKeyDown}
							className="w-full p-1 px-2 -ml-2 text-xs sm:text-sm text-slate-600 bg-white border border-indigo-300 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
							rows={2}
						/>

						<p className="text-xs text-slate-400 flex items-center justify-center gap-1">
							Tekan "ESC" untuk membatalkan edit
						</p>
					</div>
				) : (
					<div title="Double klik untuk mengedit">
						<h3
							className={`font-semibold text-sm sm:text-base break-words transition-all ${
								todo.completed
									? "text-slate-400 line-through"
									: "text-slate-800"
							}`}>
							{todo.title == editTitle ? todo.title : editTitle}
						</h3>
						<p
							className={`text-xs sm:text-sm break-words whitespace-pre-wrap ${
								todo.completed ? "text-slate-300" : "text-slate-500"
							}`}>
							{todo.description == editDesc ? todo.description : editDesc}
						</p>
					</div>
				)}
			</div>

			{/* Delete Button */}
			{!isEditing && (
				<button
					onClick={() => onDelete(todo.id)}
					className="opacity-0 group-hover:opacity-100 p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all mt-1"
					title="Hapus Tugas">
					{loading ? (
						<div className="w-4 h-4 border-2 border-red-500 border-t-transparent rounded-full animate-spin" />
					) : (
						<Trash2 size={18} />
					)}
				</button>
			)}
		</div>
	);
};
