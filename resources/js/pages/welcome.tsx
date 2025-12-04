import React, { useState } from "react";
import axios from "axios";

const ChatForm: React.FC = () => {
  const [prompt, setPrompt] = useState("");
  const [imageBase64, setImageBase64] = useState<string | null>(null);
  const [role, setRole] = useState<"user" | "system" | "assistant">("user");
  const [response, setResponse] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  // Funkcja do konwersji pliku na base64
  const handleImageChange = (file: File | null) => {
    if (!file) {
      setImageBase64(null);
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      setImageBase64(reader.result as string); // base64 string
    };
    reader.readAsDataURL(file);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setResponse(null);

    try {
      const body = {
        prompt,
        role,
        image:imageBase64, // wysyłamy base64 zamiast pliku
      };

      const { data } = await axios.post("/api/send", body, {
        headers: {
          "Content-Type": "application/json",
        },
      });

      setResponse(data);
    } catch (err: any) {
      if (err.response) {
        setResponse(err.response.data);
      } else {
        setResponse({ error: err.message });
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-4 max-w-xl mx-auto">
      <h1 className="text-xl font-bold mb-4">Wyślij prompt i obraz (base64)</h1>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <label className="flex flex-col">
          Prompt:
          <textarea
            value={prompt}
            onChange={(e) => setPrompt(e.target.value)}
            required
            rows={4}
            className="border p-2 rounded"
          />
        </label>

        <label className="flex flex-col">
          Obraz (opcjonalnie):
          <input
            type="file"
            accept="image/*"
            onChange={(e) => handleImageChange(e.target.files?.[0] || null)}
          />
        </label>

        {imageBase64 && (
          <img
            src={imageBase64}
            alt="Podgląd"
            className="w-48 h-auto border mt-2"
          />
        )}

        <label className="flex flex-col">
          Rola:
          <select value={role} onChange={(e) => setRole(e.target.value as any)}>
            <option value="user">User</option>
            <option value="system">System</option>
            <option value="assistant">Assistant</option>
          </select>
        </label>

        <button
          type="submit"
          className="bg-blue-500 text-white px-4 py-2 rounded"
          disabled={loading}
        >
          {loading ? "Wysyłanie..." : "Wyślij"}
        </button>
      </form>

      {response && (
        <div className="mt-6">
          <h2 className="font-bold mb-2">Odpowiedź:</h2>
          <pre className="bg-gray-100 p-4 rounded overflow-auto">
            {JSON.stringify(response, null, 2)}
          </pre>
        </div>
      )}
    </div>
  );
};

export default ChatForm;
