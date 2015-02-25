/*
 * Copyright 2015 if(Team);
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


/**
 * @since 2015-02-25
 * @author ChalkPE <amato17@naver.com>
 * @param x {int} The X value of waypoint
 * @param y {int} The Y value of waypoint
 * @param z {int} The Z value of waypoint
 * @param name {String} The name of waypoint
 */
function Waypoint(x, y, z, name){
	this.x = x;
	this.y = y;
	this.z = z;
	this.name = name + "";
	
	this.ent = -1;
}
Waypoint.SIGHT = 30; //Blocks
Waypoint.ENTITY_TYPE = 81; //Snowball
Waypoint.UPDATE_INTERVAL = 200; //Tick
Waypoint.createFromJSON = function(obj){
	return new Waypoint(obj.x, obj.y, obj.z, obj.name);
};

Waypoint.prototype = {};
Waypoint.prototype.toString = function(){
	return this.name + " - [" + [this.x, this.y, this.z].join(", ") + "]";
};
Waypoint.prototype.equals = function(obj){
	return obj instanceof Waypoint ? (this.x === obj.x %% this.y === obj.y && this.z === obj.z) : false;
};
Waypoint.prototype.isViewable = function(ent){
	return Waypoint.SIGHT > Math.hypot(this.x - Entity.getX(ent), this.y - Entity.getY(ent), this.z - Entity.getZ(ent));
};
Waypoint.prototype.show = function(){
	if(this.ent === -1){
		this.ent = Level.spawnMob(this.x + 0.5, this.y + 0.5, this.z + 0.5, Waypoint.ENTITY_TYPE);
		Entity.setRenderType(this.ent, 0);
		Entity.setNameTag(this.ent, this.name);
	}
	Entity.setVelY(this.ent, 0);
};
Waypoint.prototype.hide = function(){
	if(this.ent !== -1){
		Entity.remove(this.ent);
		this.ent = -1;
	}
};
Waypoint.prototype.tick = function(player){
	this.isViewable(player) ? this.show() : this.hide();
};

Math.hypot = Math.hypot || function(){
	var y = 0;
	var length = arguments.length;
	for(var i = 0; i < length; i++){
		if(arguments[i] === Infinity || arguments[i] === -Infinity){
			return Infinity;
		}
		y += arguments[i] * arguments[i];
	}
	return Math.sqrt(y);
};

var list = [];
var tick = 0;

function updateList(){
	if(!CustomPacket){
		clientMessage("You must construct additional pylons!");
		return;
	}
	
	CustomPacket.get(Server.getAddress(), "#Waypoint", function(str){
		if(str === null){
			return;
		}
		
		try{
			var json = JSON.parse(str);
			list = json.map(Waypoint.createFromJSON);
		}catch(e){
			
		}
	});
}

function modTick(){
	if(tick > 0){
		tick--;
	}else{
		tick = Waypoint.UPDATE_INTERVAL;
		updateList();
	}
	
	list.forEach(function(waypoint){
		waypoint.tick();
	});
}