import React, { useState, useEffect, useCallback } from "react";
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
} from "@dnd-kit/core";
import {
	arrayMove,
	SortableContext,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import {
	PlusCircle,
	LayoutList,
	CheckCircle,
	GripVertical,
} from "lucide-react";
import { Todo, CreateTodo } from "@/types";
import { TodoItem } from "@/components/TodoItem";
import { createTodo, deleteTodo, getTodoList, updateTodo } from "@/api/todo";

const TodoListPage: React.FC = () => {
	const [todos, setTodos] = useState<Todo[]>([]);
	const [newTitle, setNewTitle] = useState("");
	const [newDescription, setNewDescription] = useState("");
	const [loadingNew, setLoadingNew] = useState<boolean>(false);
	const [loadingDel, setLoadingDel] = useState<boolean>(false);

	// Initialize sensors for drag and drop
	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		}),
	);

	// Load data from API
	useEffect(() => {
		const fetchTodoList = async () => {
			const todoList = await getTodoList();

			todoList.data.map((todo) => (todo.completed = todo.is_done));

			setTodos(todoList.data);
		};

		fetchTodoList();
	}, []);

	const handleSubmit = async (e: any) => {
		e.preventDefault();
		if (!newTitle.trim()) return;

		const payload: CreateTodo = {
			title: e.target.title?.value,
			description: e.target.description?.value,
		};

		setLoadingNew(true);
		const send = await createTodo(payload);

		if (send?.code == 201) {
			setTodos((prev) => [payload, ...prev]);

			payload["id"] = send.data.id;
		}

		setLoadingNew(false);
		setNewTitle("");
		setNewDescription("");
	};

	const handleUpdateTodo = async (id: number, payload) => {
		const todo = todos.find((todo) => todo.id === id);

		const toggle = await updateTodo(id, payload);

		if (toggle?.code == 200) {
			setTodos((prev) =>
				prev.map((todo) =>
					todo.id === id
						? {
								...todo,
								title: payload.title,
								description: payload.description,
							}
						: todo,
				),
			);
		}
	};

	const handleDeleteTodo = async (id: number) => {
		setLoadingDel(true);

		const send = await deleteTodo(id);

		if (send?.code == 200) {
			setTodos((prev) => prev.filter((todo) => todo.id !== id));
		}

		setLoadingDel(false);
	};

	const handleToggleTodo = async (id: number) => {
		const todo = todos.find((todo) => todo.id === id);

		const completed = !todo.completed;

		const toggle = await updateTodo(id, { is_done: completed });

		if (toggle?.code == 200) {
			setTodos((prev) =>
				prev.map((todo) =>
					todo.id === id ? { ...todo, completed: completed } : todo,
				),
			);
		}
	};

	const handleDragEnd = (event: any) => {
		const { active, over } = event;

		if (over && active.id !== over.id) {
			setTodos((items) => {
				const oldIndex = items.findIndex((t) => t.id === active.id);
				const newIndex = items.findIndex((t) => t.id === over.id);
				return arrayMove(items, oldIndex, newIndex);
			});
		}
	};

	const completedCount = todos.filter((t) => t.completed).length;
	const progress =
		todos.length === 0 ? 0 : Math.round((completedCount / todos.length) * 100);

	return (
		<div className="h-screen bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
			<div className="w-full mx-auto">
				{/* Progress Bar */}
				{todos.length > 0 && (
					<div className="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6 transition-all animate-in fade-in slide-in-from-top-4 duration-500">
						<div className="flex items-center justify-between mb-2">
							<span className="text-sm font-medium text-slate-600">
								Progres Tugas
							</span>
							<span className="text-sm font-bold text-red-600">
								{progress}%
							</span>
						</div>
						<div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
							<div
								className="bg-red-600 h-full transition-all duration-700 ease-out"
								style={{ width: `${progress}%` }}
							/>
						</div>
						<div className="flex items-center gap-1 mt-2 text-xs text-slate-400">
							<CheckCircle size={12} className="text-green-500" />
							<span>
								{completedCount} dari {todos.length} selesai
							</span>
						</div>
					</div>
				)}

				{/* Input Section */}
				<div className="relative mb-8">
					<form onSubmit={handleSubmit} className="group flex items-top gap-2">
						<div className="relative flex-grow">
							<input
								type="text"
								value={newTitle}
								name="title"
								onChange={(e) => setNewTitle(e.target.value)}
								placeholder="Tulis todo list disini"
								className="w-full p-4 bg-white border border-slate-200 rounded-2xl shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all disabled:opacity-50"
							/>
							<textarea
								style={{
									resize: "none",
								}}
								value={newDescription}
								onChange={(e) => setNewDescription(e.target.value)}
								name="description"
								placeholder="Deskripsi todo list disini"
								className="min-h-[100px] w-full mt-4 p-4 bg-white border border-slate-200 rounded-2xl shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all disabled:opacity-50"
							/>
						</div>
						<button
							type="submit"
							disabled={!newTitle.trim()}
							className="p-4 bg-primary text-white rounded-2xl shadow-lg shadow-red-200 hover:bg-red-700 active:scale-95 transition-all disabled:opacity-50 disabled:scale-100 disabled:shadow-none">
							{loadingNew ? "" : <PlusCircle size={24} />}
						</button>
					</form>
				</div>

				{/* Todo List Content */}
				<div className="space-y-4">
					{todos.length === 0 ? (
						<div className="flex flex-col items-center justify-center py-20 text-center bg-white border-2 border-dashed border-slate-200 rounded-3xl opacity-60">
							<div className="p-4 bg-slate-50 rounded-full mb-4">
								<LayoutList size={32} className="text-slate-300" />
							</div>
							<h3 className="text-slate-500 font-medium">Belum ada tugas</h3>
							<p className="text-slate-400 text-sm">
								Tambahkan satu di atas untuk memulai!
							</p>
						</div>
					) : (
						<DndContext
							sensors={sensors}
							collisionDetection={closestCenter}
							onDragEnd={handleDragEnd}>
							<SortableContext
								items={todos}
								strategy={verticalListSortingStrategy}>
								<div className="flex flex-col">
									{todos.map((todo, key) => (
										<TodoItem
											key={key}
											todo={todo}
											loading={loadingDel}
											// onChange={handleChangeTodo}
											onToggle={handleToggleTodo}
											onDelete={handleDeleteTodo}
											onUpdate={handleUpdateTodo}
										/>
									))}
								</div>
							</SortableContext>
						</DndContext>
					)}
				</div>

				{/* Footer Info */}
				{todos.length > 1 && (
					<div className="block py-8 text-center">
						<p className="text-xs text-slate-400 flex items-center justify-center gap-1">
							<GripVertical size={14} />
							Tahan dan geser ikon handle di sebelah kiri untuk mengatur urutan
						</p>
					</div>
				)}
			</div>
		</div>
	);
};

export default TodoListPage;
