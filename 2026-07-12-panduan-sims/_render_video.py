#!/usr/bin/env python3
import json, os, subprocess, textwrap, sys
BASE = os.path.dirname(os.path.abspath(__file__))
IMG  = os.path.join(BASE, "img")
OUT  = os.path.join(BASE, "video")
TMP  = os.path.join(BASE, "_tmp")
FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
FONTB= "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
os.makedirs(OUT, exist_ok=True); os.makedirs(TMP, exist_ok=True)
data = json.load(open(os.path.join(BASE, "_sb.json")))

only=None; sl0=None; sl1=None
args=sys.argv[1:]
if len(args)==1 and args[0] not in ("all","ALL",""):
    only=args[0]
elif len(args)==2:
    sl0=int(args[0]); sl1=sl0+int(args[1])

def esc_path(p): return p.replace("\\","/").replace(":","\\:")
def wtext(name,s):
    p=os.path.join(TMP,name); open(p,"w").write(s); return esc_path(p)
def draw(tf,x,y,size,color,bold=True,a=None,b=None):
    f=FONTB if bold else FONT
    s=(f"drawtext=fontfile='{f}':textfile='{tf}':x={x}:y={y}:"
       f"fontsize={size}:fontcolor={color}:line_spacing=6")
    if a is not None: s+=f":enable='between(t,{a},{b})'"
    return s

W,H=1280,720
made=[]
for idx,f in enumerate(data):
    if sl0 is not None and not (sl0<=idx<sl1): continue
    img=os.path.join(IMG,f["img"])
    if not os.path.exists(img): continue
    if only and f["id"]!=only: continue
    D=float(f["dur"]); fps=30; frames=int(D*fps)
    n,title,path=f["n"],f["title"],f["path"]
    tf_title=wtext(f"{f['id']}_t.txt", f"{n}. {title}")
    tf_path =wtext(f"{f['id']}_p.txt", path)
    intro=f"FITUR {n}\n{title}\n\n{path}\n\nUntuk: {textwrap.fill(f['users'],54)}"
    tf_intro=wtext(f"{f['id']}_i.txt", intro)
    narr=f["narr"].replace('“','"').replace('”','"').replace('’',"'")
    tf_narr=wtext(f"{f['id']}_n.txt", "NARASI:  "+textwrap.fill(narr,92))
    shot_tfs=[]
    for i,sh in enumerate(f["shots"]):
        line=f"SHOT {sh[0]} - {sh[1]}   >>   {sh[2]}"
        shot_tfs.append(wtext(f"{f['id']}_s{i}.txt", textwrap.fill(line,96)))
    intro_end=3.0; close_dur=4.5
    seg_start=intro_end; seg_end=D-close_dur
    k=len(shot_tfs); seg=max(0.1,(seg_end-seg_start)/k)
    parts=[]
    parts.append(
      "[0:v]scale=1180:520:force_original_aspect_ratio=decrease,"
      "pad=1180:520:(ow-iw)/2:(oh-ih)/2:color=0x0f172a,setsar=1,"
      f"zoompan=z=1:d={frames}:s=1180x520:fps={fps}[kb]")
    parts.append(f"color=c=0x0f172a:s={W}x{H}:d={D}:r={fps}[bg]")
    parts.append("[bg][kb]overlay=x=50:y=74[v0]")
    fc=["[v0]drawbox=x=0:y=0:w=1280:h=62:color=0x1e3a8a@0.95:t=fill",
        "drawbox=x=0:y=582:w=1280:h=138:color=black@0.66:t=fill"]
    fc.append(draw(tf_title,28,16,30,"white"))
    fc.append(draw(tf_path,"w-tw-28",22,20,"0x93c5fd",bold=False))
    for i,stf in enumerate(shot_tfs):
        a=seg_start+i*seg
        b=seg_start+(i+1)*seg if i<k-1 else seg_end
        fc.append(draw(stf,40,604,25,"white",a=a,b=b))
    fc.append(draw(tf_narr,40,662,22,"0xfde68a",bold=False,a=intro_end,b=D))
    fc.append(f"drawbox=x=0:y=0:w={W}:h={H}:color=0x0f172a@1:t=fill:enable='between(t,0,{intro_end})'")
    fc.append(draw(tf_intro,96,214,42,"white",a=0,b=intro_end))
    fc.append(f"drawbox=x=96:y=192:w=470:h=6:color=0x16a34a:t=fill:enable='between(t,0,{intro_end})'")
    parts.append(",".join(fc)+"[vout]")
    filt=";".join(parts)
    outp=os.path.join(OUT,f["id"]+".mp4")
    cmd=["ffmpeg","-y","-loglevel","error","-loop","1","-t",str(D),"-i",img,
         "-filter_complex",filt,"-map","[vout]",
         "-c:v","libx264","-pix_fmt","yuv420p","-r",str(fps),"-t",str(D),outp]
    r=subprocess.run(cmd,capture_output=True,text=True)
    if r.returncode!=0:
        print(f"GAGAL {f['id']}: {r.stderr[-400:]}"); continue
    made.append(f["id"]); print(f"OK {f['id']}.mp4 {D:.0f}s {os.path.getsize(outp)//1024}KB")
print(f"Total: {len(made)}")
