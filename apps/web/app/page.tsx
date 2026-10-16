import "./styled.css";

export default function Home() {
  return (
    <main className="pattern-bg relative flex flex-1 flex-col items-center justify-center overflow-hidden px-6 text-zinc-50">

      <div className="relative flex max-w-xl flex-col items-center gap-6 text-center">

        <h1 className="text-5xl font-semibold tracking-tight sm:text-7xl">
          Mirai
        </h1>

        <p className="text-base leading-7 text-zinc-400 sm:text-lg">
          Our online store is on its way. Computers, parts and accessories,
          all in one place.
        </p>
      </div>

      <footer className="absolute bottom-6 text-xs text-zinc-600">
        © {new Date().getFullYear()} Mirai
      </footer>
    </main>
  );
}
