var ctx = com.mojang.minecraftpe.MainActivity.currentMainActivity.get();
var root = new java.io.File(android.os.Environment.getExternalStorageDirectory().getAbsoluteFile(), "games/com.mojang/minecraftResources/LOCK2.0");

var tick = 10;

function runOnThread(func){
	new java.lang.Thread({run: func}).start();
}

function runOnUiThread(func){
	ctx.runOnUiThread(new java.lang.Runnable({run: func}));
}

var Conditions = {
		enabled: false,
		activated: false
};

var Resources = {
		mediaPlayer: null,

		startMusic: function(musicName){
			var file = new java.io.File(root, musicName);
			if(file.exists() === false){
				clientMessage("음악 파일이 존재하지 않습니다.");
				return;
			}

			try{
				if(Resources.mediaPlayer.isPlaying()){
					Resources.mediaPlayer.pause();
				}
				Resources.mediaPlayer.reset();
				Resources.mediaPlayer.setDataSource(file.getAbsolutePath());
				Resources.mediaPlayer.prepare();
				Resources.mediaPlayer.start();
			}catch(e){
				clientMessage("음악 파일 재생에 실패했습니다. \n" + e.getMessage());
			}
		},

		stopMusic: function(){
			if(Resources.mediaPlayer.isPlaying()){
				Resources.mediaPlayer.stop();
				Resources.mediaPlayer.prepare();
				Resources.mediaPlayer.seekTo(0);
			}
		},

		finishMusic: function(){
			runOnUiThread(function(){
				if(Resources.mediaPlayer !== null){
					Resources.mediaPlayer.release();
					Resources.mediaPlayer = null;
				}
			});
		}
};

var GUI = {
		window: null,
		imageViewA: null,

		create: function(){runOnUiThread(function(){
			try{
				var layout = new android.widget.FrameLayout(ctx);
				layout.setLayoutParams(new android.widget.FrameLayout.LayoutParams(android.widget.FrameLayout.LayoutParams.MATCH_PARENT, android.widget.FrameLayout.LayoutParams.MATCH_PARENT));

				GUI.imageViewA = new android.widget.ImageView(ctx);
				GUI.imageViewA.setLayoutParams(new android.widget.FrameLayout.LayoutParams(android.widget.FrameLayout.LayoutParams.MATCH_PARENT, android.widget.FrameLayout.LayoutParams.MATCH_PARENT));

				layout.addView(GUI.imageViewA);

				GUI.window = new android.widget.PopupWindow(layout, ctx.getWindowManager().getDefaultDisplay().getWidth(), ctx.getWindowManager().getDefaultDisplay().getHeight(), true);
				GUI.window.setTouchable(false);
				GUI.window.setFocusable(false);
				GUI.window.setOutsideTouchable(true);
				GUI.window.showAtLocation(ctx.getWindow().getDecorView(), android.view.Gravity.CENTER, 0, 0);
			}catch(e){
				clientMessage("GUI 생성에 실패했습니다. \n" + e.getMessage());
			}
		});},

		remove: function(){runOnUiThread(function(){
			if(GUI.window !== null){
				GUI.window.dismiss();
				GUI.window = null;
			}
		});},

		setImage: function(drawable){runOnUiThread(function(){
			if(GUI.imageViewA !== null){
				GUI.imageViewA.setBackgroundDrawable(drawable);
			}
		});},

		createDrawable: function(drawableName){
			var file = new java.io.File(root, drawableName);
			if(file.exists() === false){
				clientMessage("이미지 파일이 존재하지 않습니다.");
				return null;
			}

			try{
				/*var bitmap = android.graphics.BitmapFactory.decodeFile(file.getAbsolutePath());
				var height = ctx.getWindowManager().getDefaultDisplay().getHeight();
				var scale = height / bitmap.getHeight();
				var width = scale * bitmap.getWidth(); //ctx.getWindowManager().getDefaultDisplay().getWidth();
				
				return new android.graphics.drawable.BitmapDrawable(android.graphics.Bitmap.createScaledBitmap(bitmap, width, height, true));
				*/
				return android.graphics.drawable.Drawable.createFromPath(file.getAbsolutePath());
			}catch(e){
				clientMessage("이미지 로드에 실패했습니다. \n" + e.getMessage());
			}
		}
};

function init(){runOnThread(function(){
	Resources.mediaPlayer = new android.media.MediaPlayer();

	Resources.NONE = GUI.createDrawable("NOSCREEN.png");
	Resources.READY = GUI.createDrawable("LOCKREADY.png");

	Resources.ACTIVE_ANIMATED = new android.graphics.drawable.AnimationDrawable();
	Resources.ACTIVE_ANIMATED.setOneShot(true);
	Resources.ACTIVE_ANIMATED.setVisible(true, true);

	for(var index = 0; index < 60; index++){
		Resources.ACTIVE_ANIMATED.addFrame(GUI.createDrawable("LOCKON/LOCK" + index + ".png"), 32);
	}

	GUI.create();

	runOnUiThread(function(){
		android.widget.Toast.makeText(ctx, "© 2014 ChalkPE. All rights reserved.", 1).show();
	});
});}

function finalize(){
	GUI.remove();
	Resources.finishMusic();
}





function getCoordinateArray(ent){
	return [Entity.getX(ent), Entity.getY(ent), Entity.getZ(ent)];
}

function killEntity(ent){
	Entity.setHealth(ent, 1);
	Entity.setFireTicks(ent, 15);

	var blockId = Level.getTile.apply(null, getCoordinateArray(ent));
	if(Entity.getEntityTypeId(ent) === 36 || (blockId === 8 || blockId === 9)){
		Entity.setHealth(ent, 0);
	}

	Level.playSound(Entity.getX(ent), Entity.getY(ent), Entity.getZ(ent), "random.explode", 2, 1);
}

function isClose(a, b, length){
	return Math.abs(a[0] - b[0]) < length &&
	Math.abs(a[1] - b[1]) < length &&
	Math.abs(a[2] - b[2]) < length;
}

function getLookingEntity(){
	try{
		var radian =  1 / 180 * Math.PI;
		var player = Player.getEntity();

		var centerX = Entity.getX(player),
		centerY = Entity.getY(player),
		centerZ = Entity.getZ(player);

		var yaw = Entity.getYaw(player),
		pitch = Entity.getPitch(player);

		for(var radius = 0; radius < 100; radius += 2){
			var coordinate = [
			                  Math.floor(centerX + radius * -Math.sin(yaw * radian) * Math.cos(pitch * radian)),
			                  Math.floor(centerY + radius * -Math.sin(pitch * radian)),
			                  Math.floor(centerZ + radius * Math.cos(yaw * radian) * Math.cos(pitch * radian))
			                  ];

			var entites = Entity.getAll();

			for(var i = 0; i < entites.length; i++){
				if(entites[i] !== player && Entity.getEntityTypeId(entites[i]) < 64 && 
						isClose(getCoordinateArray(entites[i]), coordinate, 2)){
					return entites[i];
				}
			}
		}
		return null;
	}catch(e){
		return null;
	}
}

function onTick(){
	var enabled = (Player.getCarriedItem() === 261);

	if(Conditions.enabled === false && enabled === false){ //NONE -> NONE

	}else if(Conditions.enabled === true && enabled === false){ //READY -> NONE
		Resources.stopMusic();
		GUI.setImage(Resources.NONE);
		runOnUiThread(function(){
			Resources.ACTIVE_ANIMATED.stop();
		});

		Conditions.enabled = enabled;
		Conditions.activated = false;
	}else if(Conditions.enabled === false && enabled === true){ //NONE -> READY
		GUI.setImage(Resources.READY);

		Conditions.enabled = enabled;
		Conditions.activated = false;
	}else{
		var looking = getLookingEntity();
		var activated = looking !== null;

		if(Conditions.activated === false && activated === true){ //READY -> ACTIVATED
			Resources.startMusic("lockon.mp3");

			GUI.setImage(Resources.ACTIVE_ANIMATED);
			runOnUiThread(function(){
				Resources.ACTIVE_ANIMATED.start();
			});

			Conditions.activated = activated;
		}else if(Conditions.activated === true && activated === false){ //ACTIVATED -> READY
			Resources.stopMusic();

			GUI.setImage(Resources.READY);
			runOnUiThread(function(){
				Resources.ACTIVE_ANIMATED.stop();
			});

			Conditions.activated = activated;
		}
	}
}

function onArrowShot(ent){
	if(Conditions.activated === false){
		return;
	}

	if(isClose(getCoordinateArray(ent), getCoordinateArray(Player.getEntity()), 2) === false){
		//플레이어와 2블럭 이상 떨어진 곳에서 스폰된 화살은 무시
		return;
	}

	var looking = getLookingEntity();
	if(looking !== null){
		Entity.remove(ent);
		killEntity(looking);
	}
}





function newLevel(){
	init();
}

function leaveGame(){
	finalize();
}

function modTick(){
	if(--tick){return;} tick = 10;
	runOnThread(onTick);
}

function entityAddedHook(ent){
	if(Entity.getEntityTypeId(ent) == 80){
		onArrowShot(ent);
	}
}